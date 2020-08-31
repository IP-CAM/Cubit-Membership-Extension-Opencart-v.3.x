<?php

class ModelExtensionModuleCubit extends Model {
    public function getOffer($cubit_offer_id) {
        $result = $this->db->query("SELECT o.*, od.name FROM " . DB_PREFIX . "cubit_offer o LEFT JOIN " . DB_PREFIX . "cubit_offer_description od ON o.cubit_offer_id=od.cubit_offer_id WHERE od.language_id='".$this->config->get('config_language_id') . "' AND o.`cubit_offer_id`='" . $cubit_offer_id . "'");
        
        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function getOffers($filter = array()) {
        $sql = "SELECT o.*, od.name FROM " . DB_PREFIX . "cubit_offer o LEFT JOIN " . DB_PREFIX . "cubit_offer_description od ON o.cubit_offer_id=od.cubit_offer_id WHERE od.language_id=". $this->config->get('config_language_id');

        if (isset($filter['status'])) {
            $sql .= " AND o.`status`='" . $filter['status'] . "'";
        }
        
        $sql .= " ORDER by o.`sort_order` ASC";
 
        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function addOffer($definition = array()) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "cubit_offer SET
            `amount`='" . (float)$definition['amount'] . "',
            `frequency_days`='" . $definition['frequency_days'] . "',
            `active_customer_group_id`='" . $definition['active_customer_group_id'] . "',
            `expire_customer_group_id`='" . $definition['expire_customer_group_id'] . "',
            `sort_order`='" . $definition['sort_order'] . "',
            `status`='" . $definition['status'] . "'"
        );

        return $this->db->getLastId();
    }

    public function addOfferDescription($cubit_offer_id, $description = array()) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_offer_description WHERE `cubit_offer_id`='" . (int)$cubit_offer_id . "' AND `language_id`='" . $description['language_id'] . "' LIMIT 1");

        $this->db->query("INSERT INTO " . DB_PREFIX . "cubit_offer_description SET `name`='" . $this->db->escape($description['name']) . "', `cubit_offer_id`='" . $cubit_offer_id . "', `language_id`='" . $description['language_id'] . "'");
    }

    public function getOfferDescriptions($cubit_offer_id) {
        $result = $this->db->query("SELECT `name`, `language_id` FROM " . DB_PREFIX . "cubit_offer_description WHERE `cubit_offer_id`='" . (int)$cubit_offer_id . "'");
        
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $descriptions = array();

        $language_column = array_column($result->rows, 'language_id');

        foreach ($languages as $language) {
            $description_index = array_search($language['language_id'], $language_column);

            if ($description_index !== false) {
                $descriptions[$language['language_id']] = $result->rows[$description_index];
            } else {
                $descriptions[$language['language_id']] = array(
                    'name' => '',
                    'language_id' => $language['language_id']
                );
            }
        }

        return $descriptions;
    }

    public function editOffer($cubit_offer_id, $definition = array()) {
        $this->db->query("UPDATE " . DB_PREFIX . "cubit_offer SET `amount`=" . (float)$definition['amount'] . ", `frequency_days`=" . (int)$definition['frequency_days'] . ", `active_customer_group_id`=" . (int)$definition['active_customer_group_id'] . ", `expire_customer_group_id`=" . (int)$definition['expire_customer_group_id'] . ", `sort_order`='" . (int)$definition['sort_order'] . "', `status`='" . $definition['status'] . "' WHERE `cubit_offer_id` LIKE '" . (int)$cubit_offer_id . "' LIMIT 1");
        
        return $this->db->countAffected();
    }

    public function deleteOffer($cubit_offer_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_offer  WHERE `cubit_offer_id` LIKE '" . (int) $cubit_offer_id . "' LIMIT 1");
        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_offer_description  WHERE `cubit_offer_id` LIKE '" . (int) $cubit_offer_id . "' LIMIT 1");
    }

    public function editCustomerGroupId($customer_id, $customer_group_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET customer_group_id='" . (int)$customer_group_id . "' WHERE customer_id='" . (int)$customer_id . "'");

        return $this->db->countAffected();
    }

    public function editPaypalSubscription($id, $redefinition) {
        $this->db->query("UPDATE " . DB_PREFIX . "cubit_paypal_subscription SET
            `update_time`='" . $redefinition['update_time'] . "',
            `status`='" . $redefinition['status'] . "'
            WHERE `id` LIKE '" . $id . "'"
        );
    }

    public function getMembership($cubit_membership_id) {
        $result =  $this->db->query("SELECT m.*, CONCAT(c.firstname, ' ', c.lastname) as customer_fullname, ps.plan as paypal_subscription_plan, ps.approve_link as paypal_subscription_approve_link, ps.create_time as paypal_subscription_create_time, ps.update_time as paypal_subscription_update_time, ps.status as paypal_subscription_status FROM " . DB_PREFIX . "cubit_membership m LEFT JOIN " . DB_PREFIX . "customer c ON m.customer_id=c.customer_id LEFT JOIN " . DB_PREFIX . "cubit_paypal_subscription ps ON ps.id=m.paypal_subscription_id WHERE `cubit_membership_id`='" . $cubit_membership_id . "'");

        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function getMemberships($filter = array()) {
        $sql = "SELECT m.*, CONCAT(c.firstname, ' ', c.lastname) as customer_fullname, ps.plan as paypal_subscription_plan, ps.approve_link as paypal_subscription_approve_link, ps.create_time as paypal_subscription_create_time, ps.update_time as paypal_subscription_update_time, ps.status as paypal_subscription_status FROM " . DB_PREFIX . "cubit_membership m LEFT JOIN " . DB_PREFIX . "customer c ON m.customer_id=c.customer_id LEFT JOIN " . DB_PREFIX . "cubit_paypal_subscription ps ON ps.id=m.paypal_subscription_id WHERE 1";

        if (isset($filter['customer_id'])) {
            $sql .= " AND m.customer_id='" . (int)$filter['customer_id'] . "'";
        }

        if (isset($filter['cubit_offer_id']) && $filter['cubit_offer_id'] > -1) {
            $sql .= " AND m.offer_id='" . (int)$filter['cubit_offer_id']  . "'";
        }

        if (isset($filter['customer_fullname'])) {
            $sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . trim($filter['customer_fullname'], '%') . "%'";
        }

        if (isset($filter['paypal_subscription_id']) && $filter['paypal_subscription_id']) {
            $sql .= " AND m.paypal_subscription_id='" . $filter['paypal_subscription_id'] . "'";
        }

        if (isset($filter['paypal_subscription_status']) && $filter['paypal_subscription_status']) {
            $sql .= " AND ps.status='" . $filter['paypal_subscription_status'] . "'";
        }

        if (isset($filter['status']) && ($filter['status'] == 0 || $filter['status'] == 1)) {
            $sql .= " AND m.status='" . (int)$filter['status']  . "'";
        }

        if (isset($filter['store_id']) && $filter['store_id'] >= 0) {
            $sql .= " AND m.store_id='" . (int)$filter['store_id']  . "'";
        }
        
      

        if (isset($filter['sort'])) {
            $sql .= " ORDER by " . $this->db->escape($filter['sort']);

            if (isset($filter['sort_order'])) {
                $sql .= " " . $filter['sort_order'];
            }
        }

        if (isset($filter['limit'])) {
            if (isset($filter['start'])) {
                $sql .= " LIMIT " . (int)$filter['start'] . ", " . (int)$filter['limit'];
            } else {
                $sql .= " LIMIT 0, " . (int)$filter['limit'];
            }
        }

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function deleteMembership($cubit_membership_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_paypal_transaction WHERE subscription=(SELECT paypal_subscription_id FROM " . DB_PREFIX . "cubit_membership WHERE cubit_membership_id=" . $cubit_membership_id . ")");

        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_paypal_subscription WHERE id=(SELECT paypal_subscription_id FROM " . DB_PREFIX . "cubit_membership WHERE cubit_membership_id=" . $cubit_membership_id . ")");

        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_membership WHERE cubit_membership_id=" . $cubit_membership_id);

    }
    
    public function getPaypalSubscription($id) {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_paypal_subscription WHERE id='" . $id . "'");

        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function getTotalMemberships($filter = array()) {
        $sql = "SELECT count(*) as total FROM " . DB_PREFIX . "cubit_membership m LEFT JOIN " . DB_PREFIX . "customer c ON m.customer_id=c.customer_id LEFT JOIN " . DB_PREFIX . "cubit_paypal_subscription ps ON ps.id=m.paypal_subscription_id WHERE 1";

        if (isset($filter['customer_id'])) {
            $sql .= " AND m.customer_id='" . (int)$filter['customer_id'] . "'";
        }

        if (isset($filter['cubit_offer_id']) && $filter['cubit_offer_id'] > -1) {
            $sql .= " AND m.offer_id='" . (int)$filter['cubit_offer_id']  . "'";
        }

        if (isset($filter['customer_fullname'])) {
            $sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . trim($filter['customer_fullname'], '%') . "%'";
        }

        if (isset($filter['paypal_subscription_id']) && $filter['paypal_subscription_id']) {
            $sql .= " AND m.paypal_subscription_id='" . $filter['paypal_subscription_id'] . "'";
        }

        if (isset($filter['paypal_subscription_status']) && $filter['paypal_subscription_status']) {
            $sql .= " AND ps.status='" . $filter['paypal_subscription_status'] . "'";
        }

        if (isset($filter['status']) && ($filter['status'] == 0 || $filter['status'] == 1)) {
            $sql .= " AND m.status='" . (int)$filter['status']  . "'";
        }

        if (isset($filter['store_id']) && $filter['store_id'] >= 0) {
            $sql .= " AND m.store_id='" . (int)$filter['store_id']  . "'";
        }
        
        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->row['total'];
        }

        return 0;
    }

    public function editMembership($cubit_membership_id, $redefinition = array()) {
        $this->db->query("set time_zone = '+00:00'");

        $this->db->query("
            UPDATE `" . DB_PREFIX . "cubit_membership` SET
            `date_ends`='" . $redefinition['date_ends'] . "',
            `status`='" . $redefinition['status'] . "'
            WHERE cubit_membership_id='" . $cubit_membership_id . "'");

        return true;
    }

    public function getPaypalTransactions($filter = array()) {
        $sql = "SELECT * FROM " . DB_PREFIX  . "cubit_paypal_transaction WHERE 1";

        if (isset($filter['id'])) {
            $sql .= " AND `id`='" . $filter['id'] . "'";
        }

        if (isset($filter['subscription'])) {
            $sql .= " AND `subscription`='" . $filter['subscription'] . "'";
        }

        if (isset($filter['state'])) {
            $sql .= " AND `state`='" . $filter['state'] . "'";
        }

        if (isset($filter['sort'])) {
            $sql .= " ORDER by " . $this->db->escape($filter['sort']);

            if (isset($filter['sort_order'])) {
                $sql .= " " . $filter['sort_order'];
            }
        }

        if (isset($filter['limit'])) {
            if (isset($filter['start'])) {
                $sql .= " LIMIT " . (int)$filter['start'] . ", " . (int)$filter['limit'];
            } else {
                $sql .= " LIMIT 0, " . (int)$filter['limit'];
            }
        }

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function getTotalPaypalTransactions($filter = array()) {
        $sql = "SELECT count(*) as total FROM " . DB_PREFIX  . "cubit_paypal_transaction WHERE 1";

        if (isset($filter['id'])) {
            $sql .= " AND `id`='" . $filter['id'] . "'";
        }

        if (isset($filter['subscription'])) {
            $sql .= " AND `subscription`='" . $filter['subscription'] . "'";
        }

        if (isset($filter['state'])) {
            $sql .= " AND `state`='" . $filter['state'] . "'";
        }

        if (isset($filter['sort'])) {
            $sql .= " ORDER by " . $this->db->escape($filter['sort']);

            if (isset($filter['sort_order'])) {
                $sql .= " " . $filter['sort_order'];
            }
        }

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->row['total'];
        }

        return 0;
    }
}