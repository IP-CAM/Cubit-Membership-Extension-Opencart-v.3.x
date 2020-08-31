<?php

class ModelExtensionModuleCubit extends Model {
    public function getOffer($offer_id) {
        $result = $this->db->query("SELECT o.*, od.name FROM " . DB_PREFIX . "cubit_offer o LEFT JOIN " . DB_PREFIX . "cubit_offer_description od ON o.cubit_offer_id=od.cubit_offer_id WHERE od.language_id='".$this->config->get('config_language_id') . "' AND o.`cubit_offer_id`='" . $offer_id . "' AND  o.`status`='1'");
        
        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function getOffers($filter = array()) {
        $sql = "SELECT o.*, od.name FROM " . DB_PREFIX . "cubit_offer o LEFT JOIN " . DB_PREFIX . "cubit_offer_description od ON o.cubit_offer_id=od.cubit_offer_id WHERE od.language_id='".$this->config->get('config_language_id') . "'";

        if (isset($filter['status'])) {
            $sql .= " AND `status`='" . $filter['status'] . "'";
        }

        $sql .= " ORDER BY o.sort_order ASC";

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function getMemberships($filter = array()) {
        $sql = "SELECT * FROM " . DB_PREFIX . "cubit_membership WHERE 1";

        if (isset($filter['customer_id'])) {
            $sql .= " AND `customer_id`='" . $filter['customer_id'] . "'";
        }

        if (isset($filter['cubit_offer_id'])) {
            $sql .= " AND `cubit_offer_id`='" . $filter['cubit_offer_id'] . "'";
        }

        if (isset($filter['paypal_subscription_id'])) {
            $sql .= " AND `paypal_subscription_id`='" . $filter['paypal_subscription_id'] . "'";
        }

        if (isset($filter['status'])) {
            $sql .= " AND `status`='" . $filter['status'] . "'";
        }

        if (isset($filter['sort'])) {
            $sql .= "ORDER BY " . $filter['sort'];

            if (isset($filter['sort_order'])) {
                $sql .= " " . $filter['sort_order'];
            }
        }

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function addMembership($definition = array()) {
        $this->db->query("set time_zone = '+00:00'");

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "cubit_membership` SET 
            `customer_id`='" . $definition['customer_id'] . "',
            `offer`='" . $this->db->escape($definition['offer']) . "',
            `offer_id`='" . $this->db->escape($definition['offer_id']) . "',
            `amount`='" . $definition['amount'] . "',
            `currency`='" . $definition['currency'] . "',
            `frequency_days`='" . $definition['frequency_days'] . "',
            `active_customer_group_id`='" . $definition['active_customer_group_id'] . "',
            `expire_customer_group_id`='" . $definition['expire_customer_group_id'] . "',
            `store_id`='" . $definition['store_id'] . "',
            `date_added`=NOW(),
            `date_renewed`=NOW(),
            `status`='" . $definition['status'] . "'"
        );

        return $this->db->getLastId();
    }

    public function getExpiredMemberships() {
        $this->db->query("set time_zone = '+00:00'");

        $result = $this->db->query("SELECT m.* FROM " . DB_PREFIX . "cubit_membership m LEFT JOIN " . DB_PREFIX . "cubit_paypal_subscription ps ON ps.id=m.paypal_subscription_id WHERE m.status=1 AND ps.status IN ('SUSPENDED', 'CANCELLED') AND m.date_ends < NOW()");
        
        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function getRenewedMemberships() {
        $this->db->query("set time_zone = '+00:00'");

        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_membership WHERE status=0 AND date_ends > NOW()");
        
        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function addPaypalSubscription($definition = array()) {
        $this->db->query("set time_zone = '+00:00'");

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "cubit_paypal_subscription` SET 
            `id`='" . $definition['id'] ."',
            `product`='" . $definition['product'] ."',
            `plan`='" . $definition['plan'] ."',
            `approve_link`='" . $definition['approve_link'] ."',
            `create_time`='" . $definition['create_time'] ."',
            `update_time`='" . $definition['create_time'] ."',
            `status`='" . $definition['status'] . "'"
        );
    }

    public function getPaypalSubscription($id) {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_paypal_subscription WHERE id LIKE '" . $id . "'");

        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function addPaypalTransaction($definition) {
        $this->db->query("set time_zone = '+00:00'");

        $this->log->write("INSERT INTO " . DB_PREFIX . "cubit_paypal_transaction SET
        `id`='" . $definition['id'] . "',
        `subscription`='" . $definition['subscription'] . "',
        `amount`='" . $definition['amount'] . "',
        `currency`='" . $definition['currency'] . "',
        `create_time`='" . $definition['create_time'] . "',
        `update_time`='" . $definition['update_time'] . "',
        `resource_type`='" . $definition['resource_type'] . "',
        `state`='" . $definition['state'] . "'");

        $this->db->query("INSERT INTO " . DB_PREFIX . "cubit_paypal_transaction SET
            `id`='" . $definition['id'] . "',
            `subscription`='" . $definition['subscription'] . "',
            `amount`='" . $definition['amount'] . "',
            `currency`='" . $definition['currency'] . "',
            `create_time`='" . $definition['create_time'] . "',
            `update_time`='" . $definition['update_time'] . "',
            `resource_type`='" . $definition['resource_type'] . "',
            `state`='" . $definition['state'] . "'"
        );
    }

    public function getPaypalTransaction($id) {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_paypal_transaction WHERE `id` LIKE '" . $id . "'");

        if ($result->rows){
            return $result->row;
        }

        return false;
    }

    public function getPaypalTransactions($filter = array()) {
        $sql = "SELECT * FROM " . DB_PREFIX . "cubit_paypal_transaction WHERE 1";

        if (isset($filter['id'])) {
            $sql .= " AND `id`='" . $filter['id'] . "'";
        }

        if (isset($filter['subscription'])) {
            $sql .= " AND `subscription`='" . $filter['subscription'] . "'";
        }

        if (isset($filter['state'])) {
            $sql .= " AND `state`='" . $filter['state'] . "'";
        }

        if (isset($filter['resource_type'])) {
            $sql .= " AND `resource_type`='" . $filter['resource_type'] . "'";
        }

        if (isset($filter['sort'])) {
            $sql .= "ORDER BY " . $filter['sort'];

            if (isset($filter['sort_order'])) {
                $sql .= " " . $filter['sort_order'];
            }
        }

        $result = $this->db->query($sql);

        if ($result->rows) {
            return $result->rows;
        }

        return array();
    }

    public function editPaypalSubscription($id, $redefinition = array()) {
        $this->db->query("set time_zone = '+00:00'");

        $this->db->query("
            UPDATE `" . DB_PREFIX . "cubit_paypal_subscription` SET 
            `update_time`='" . $redefinition['update_time'] ."',
            `status`='" . $redefinition['status'] . "'
            WHERE id LIKE '" . $id . "'"
        );
    }

    public function deleteMembership($cubit_membership_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cubit_membership WHERE `cubit_membership_id`='" . (int)$cubit_membership_id . "'");
    }

    public function customerMembershipsDisplayOff($customer_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "cubit_membership SET display=0 WHERE customer_id='" . (int)$customer_id . "'");
    }

    public function editMembership($cubit_membership_id, $redefinition = array()) {
        $this->db->query("set time_zone = '+00:00'");

        $this->db->query("
            UPDATE `" . DB_PREFIX . "cubit_membership` SET
            `paypal_subscription_id`='" . $redefinition['paypal_subscription_id'] . "',
            `date_renewed`='" . $redefinition['date_renewed'] . "',
            `date_ends`='" . $redefinition['date_ends'] . "',
            `status`='" . $redefinition['status'] . "'
            WHERE cubit_membership_id='" . $cubit_membership_id . "'");

        return true;
    }

    public function getMembershipByCustomerId($customer_id) {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_membership WHERE `customer_id` LIKE '" . $customer_id . "' ORDER BY `date_added` DESC LIMIT 1");
        
        if ($result->rows) {
            return $result->row;
        }

        return false;   
    }

    public function getMembershipByPaypalSubscriptionId($paypal_subscription_id) {
        $result = $this->db->query("SELECT * FROM " . DB_PREFIX . "cubit_membership WHERE `paypal_subscription_id`='" . $paypal_subscription_id . "' ORDER BY `date_added` DESC LIMIT 1");
        
        if ($result->rows) {
            return $result->row;
        }

        return false;
    }

    public function editCustomerGroupId($customer_id, $customer_group_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "customer SET customer_group_id='" . $customer_group_id . "' WHERE customer_id='" . (int)$customer_id . "'");

        return $this->db->countAffected();
    }
}