<?php

define('PAYPAL_SUBSCRIPTION_STATUS_APPROVAL_PENDING', 'APPROVAL_PENDING');
define('PAYPAL_SUBSCRIPTION_STATUS_APPROVED', 'APPROVED');
define('PAYPAL_SUBSCRIPTION_STATUS_ACTIVE', 'ACTIVE');
define('PAYPAL_SUBSCRIPTION_STATUS_SUSPENDED', 'SUSPENDED');
define('PAYPAL_SUBSCRIPTION_STATUS_CANCELLED', 'CANCELLED');
define('PAYPAL_SUBSCRIPTION_STATUS_EXPIRED', 'EXPIRED');

class ControllerExtensionModuleCubit extends Controller {
    private $errors = array();

    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->library('cubit');

        $this->registry->set('cubit', new Cubit($this->registry));
    }

    public function index() {
        $this->load->language('extension/module/cubit');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->load->model('extension/module/cubit');

            if (!isset($this->request->post['module_cubit_paypal_sandbox'])) {
                $this->request->post['module_cubit_paypal_sandbox'] = 0;
            } 

            if (!isset($this->request->post['module_cubit_notify_expire'])) {
                $this->request->post['module_cubit_notify_expire'] = 0;
            } 

            if (!isset($this->request->post['module_cubit_currency_convert'])) {
                $this->request->post['module_cubit_currency_convert'] = 0;
            } 

            if (isset($this->request->post['module_cubit_offer'])) {
                $offers = $this->request->post['module_cubit_offer'];

                foreach ($offers as $cubit_offer_id => $offer) {
                    $this->model_extension_module_cubit->editOffer($cubit_offer_id, $offer);
    
                    foreach ($offer['name'] as $language_id => $name) {
                        $this->model_extension_module_cubit->addOfferDescription($cubit_offer_id, array(
                            'name' => $name,
                            'language_id' => $language_id
                        ));
                    }
                }
    
                unset($this->request->post['module_cubit_offer']);
            }

            $this->model_setting_setting->editSetting('module_cubit', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'type=module&user_token=' . $this->request->get['user_token'], true));
        }

        $settings = $this->model_setting_setting->getSetting('module_cubit');

        $data = array();

        if ($this->errors) {
            $data['error_warning'] = $this->language->get('error_form');

            foreach ($this->errors as $field => $error) {
                $data[$field .  '_error'] = $error;
            }
        } else {
            $data['error_warning'] = null;
        }

        if (isset($this->request->post['module_cubit_paypal_client_id'])) {
            $data['paypal_client_id'] = $this->request->post['module_cubit_paypal_client_id'];
        } else {
            if (isset($settings['module_cubit_paypal_client_id'])) {
                $data['paypal_client_id'] = $settings['module_cubit_paypal_client_id'];
            } else {
                $data['paypal_client_id'] = '';
            }
        }

        if (isset($this->request->post['module_cubit_paypal_secret'])) {
            $data['paypal_secret'] = $this->request->post['module_cubit_paypal_secret'];
        } else {
            if (isset($settings['module_cubit_paypal_secret'])) {
                $data['paypal_secret'] = $settings['module_cubit_paypal_secret'];
            } else {
                $data['paypal_secret'] = '';
            }
        }

        if (isset($this->request->post['module_cubit_paypal_sandbox'])) {
            $data['paypal_sandbox'] = $this->request->post['module_cubit_paypal_sandbox'];
        } else {
            if (isset($settings['module_cubit_paypal_sandbox'])) {
                $data['paypal_sandbox'] = $settings['module_cubit_paypal_sandbox'];
            } else {
                $data['paypal_sandbox'] = '';
            }
        }
        
        if (isset($this->request->post['module_cubit_payment_failure_threshold'])) {
            $data['payment_failure_threshold'] = $this->request->post['module_cubit_payment_failure_threshold'];
        } else {
            if (isset($settings['module_cubit_payment_failure_threshold'])) {
                $data['payment_failure_threshold'] = $settings['module_cubit_payment_failure_threshold'];
            } else {
                $data['payment_failure_threshold'] = 1;
            }
        }

        if (isset($this->request->post['module_cubit_terms_option'])) {
            $data['terms_option'] = $this->request->post['module_cubit_terms_option'];
        } else {
            if (isset($settings['module_cubit_terms_option'])) {
                $data['terms_option'] = $settings['module_cubit_terms_option'];
            } else {
                $data['terms_option'] = 'disabled';
            }
        }

        if (isset($this->request->post['module_cubit_terms'])) {
            $data['terms'] = $this->request->post['module_cubit_terms'];
        } else {
            if (isset($settings['module_cubit_terms'])) {
                $data['terms'] = $settings['module_cubit_terms'];
            } else {
                $data['terms'] = array();
            }
        }

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $this->load->model('extension/module/cubit');

        $offers = $this->model_extension_module_cubit->getOffers();

        if (isset($this->request->post['module_cubit_offer'])) {
            $data['offers'] = $this->request->post['module_cubit_offer'];
        } else {
            if ($offers) {
                foreach ($offers as $offer) {
                    $descriptions = $this->model_extension_module_cubit->getOfferDescriptions($offer['cubit_offer_id']);

                    $offer['name'] = array();

                    foreach ($descriptions as $description) {
                        $offer['name'][$description['language_id']] = $description['name'];
                    }

                    $data['offers'][] = $offer;
                }
            } else {
                $data['offers'] = array();
            }
        }

        if (isset($this->request->post['module_cubit_notify_expire'])) {
            $data['notify_expire'] = $this->request->post['module_cubit_notify_expire'];
        } else {
            if (isset($settings['module_cubit_notify_expire'])) {
                $data['notify_expire'] = $settings['module_cubit_notify_expire'];
            } else {
                $data['notify_expire'] = '';
            }
        }

        if (isset($this->request->post['module_cubit_currency_convert'])) {
            $data['currency_convert'] = $this->request->post['module_cubit_currency_convert'];
        } else {
            if (isset($settings['module_cubit_currency_convert'])) {
                $data['currency_convert'] = $settings['module_cubit_currency_convert'];
            } else {
                $data['currency_convert'] = '1';
            }
        }

        if (isset($this->request->post['module_cubit_status'])) {
            $data['status'] = $this->request->post['module_cubit_status'];
        } else {
            if (isset($settings['module_cubit_status'])) {
                $data['status'] = $settings['module_cubit_status'];
            } else {
                $data['status'] = 0;
            }
        }

        if (isset($this->request->post['module_cubit_sort_order'])) {
            $data['sort_order'] = $this->request->post['module_cubit_sort_order'];
        } else {
            if (isset($settings['module_cubit_sort_order'])) {
                $data['sort_order'] = $settings['module_cubit_sort_order'];
            } else {
                $data['sort_order'] = 0;
            }
        }

        if (isset($settings['module_cubit_salepoint_layout_id'])) {
            $data['salepoint_layout_id'] = $settings['module_cubit_salepoint_layout_id'];
        } else {
            $data['salepoint_layout_id'] = '';
        }

        $this->load->model('customer/customer_group');

        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        $data['breadcrumbs'] = array(
            [   
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->request->get['user_token'], true),
                'text' => $this->language->get('text_home')
            ],
            [   
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->request->get['user_token'], true),
                'text' => $this->language->get('text_extension')
            ],
            [   
                'href' => $this->url->link('extension/module/cubit', 'user_token=' . $this->request->get['user_token'], true),
                'text' => $this->language->get('heading_title')
            ],
        );

        if (isset($this->request->post['module_cubit_paypal_webhook_id'])) {
            $paypal_webhook_id = $this->request->post['module_cubit_paypal_webhook_id'];
        } else {
            if ($this->config->get('module_cubit_paypal_webhook_id')) {
                $paypal_webhook_id = $this->config->get('module_cubit_paypal_webhook_id');
            } else {
                $paypal_webhook_id = '';
            }
        }

        $data['paypal_webhook_id'] =  $paypal_webhook_id;

        if (isset($this->request->post['module_cubit_paypal_sandbox_webhook_id'])) {
            $paypal_sandbox_webhook_id = $this->request->post['module_cubit_paypal_sandbox_webhook_id'];
        } else {
            if ($this->config->get('module_cubit_paypal_sandbox_webhook_id')) {
                $paypal_sandbox_webhook_id = $this->config->get('module_cubit_paypal_sandbox_webhook_id');
            } else {
                $paypal_sandbox_webhook_id = '';
            }
        }

        $data['paypal_sandbox_webhook_id'] =  $paypal_sandbox_webhook_id;

        $data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/module/cubit/cron';

        $this->load->model('localisation/language');

        $data['languages'] =$this->model_localisation_language->getLanguages();

        $data['user_token'] = $this->request->get['user_token'];

        $data['action'] = $this->url->link('extension/module/cubit', 'user_token='.$this->request->get['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/cubit', $data));
    }

    public function addOffer() {
        $this->load->language('extension/module/cubit');
        $this->load->model('extension/module/cubit');

        $default_offer = array(
            'amount' => 2.99,
            'frequency_days' => 1,
            'active_customer_group_id' => $this->config->get('config_customer_group_id'),
            'expire_customer_group_id' => $this->config->get('config_customer_group_id'),
            'sort_order' => 0,
            'status' => 0
        );

        $cubit_offer_id = $this->model_extension_module_cubit->addOffer($default_offer);

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $offer_descriptions = array();

        foreach ($languages as $language) {
            $description = array(
                'language_id' => $language['language_id'],
                'name' => 'Offer #' . $cubit_offer_id 
            );

            $offer_descriptions[$language['language_id']] = 'Offer #' . $cubit_offer_id;

            $this->model_extension_module_cubit->addOfferDescription($cubit_offer_id, $description);
        }

        $this->load->model('customer/customer_group');

        $data = array(
            'languages' => $languages,
            'offer' =>  array_merge(
                array('cubit_offer_id' => $cubit_offer_id),
                $default_offer,
                array('name' => $offer_descriptions)
            ),
            'customer_groups' => $this->model_customer_customer_group->getCustomerGroups()
        );

        die($this->load->view('extension/module/cubit_offer', $data));
    }

    public function regenerateWebhook() {
        header('Content-type: application/json');

        $this->load->language('extension/module/cubit');

        if (!$this->user->hasPermission('modify', 'extension/module/cubit')) {
            die(json_encode(array('error' => $this->language->get('error_persmission'))));
        }

        if (!isset($this->request->post['env']) || !isset($this->request->post['client_id']) || !isset($this->request->post['secret'])) {
            die(json_encode(array('error' => $this->language->get('error_request'))));
        }

        $paypal_client_id = $this->request->post['client_id'];
        $paypal_secret = $this->request->post['secret'];

        $sandbox = $this->request->post['env'] == 'sandbox' ? true : false;
        
        try {
            $access_token = $this->cubit->getPaypalAccessToken($paypal_client_id, $paypal_secret, $sandbox);

            if (!$access_token) {
                die(json_encode(array('error' => $this->language->get('error_paypal_authentication'))));
            }

            $paypal_webhook_list_url = '';

            if ($sandbox) {
                $paypal_webhook_list_url = 'https://api.sandbox.paypal.com/v1/notifications/webhooks';
            } else {
                $paypal_webhook_list_url = 'https://api.paypal.com/v1/notifications/webhooks';
            }

            $webhook_list_request = $this->cubit->sendPaypalRequest('GET', $paypal_webhook_list_url, $access_token);

            if ($webhook_list_request['status'] == 200) {

                
                $webhook_duplicate_index = array_search(HTTPS_CATALOG . 'index.php?route=extension/module/cubit/webhook&autogen=true', array_column($webhook_list_request['body']['webhooks'], 'url'));

                if ($webhook_duplicate_index !== false) {
                    //DELETE old
                    $webhook_delete_url = '';

                    if ($sandbox) {
                        $paypal_webhook_delete_url = 'https://api.sandbox.paypal.com/v1/notifications/webhooks/' . $webhook_list_request['body']['webhooks'][$webhook_duplicate_index]['id'];
                    } else {
                        $paypal_webhook_delete_url = 'https://api.paypal.com/v1/notifications/webhooks/' . $webhook_list_request['body']['webhooks'][$webhook_duplicate_index]['id'];
                    }

                    $this->cubit->sendPaypalRequest('DELETE', $paypal_webhook_delete_url, $access_token, array(), array());
                }
            } else {
                die(json_encode(array('error' =>$webhook_create_request['body']['message'])));
            }

            $paypal_webhook_create_url = '';

            if ($sandbox) {
                $paypal_webhook_create_url = 'https://api.sandbox.paypal.com/v1/notifications/webhooks';
            } else {
                $paypal_webhook_create_url = 'https://api.paypal.com/v1/notifications/webhooks';
            }

            if (stripos(HTTPS_CATALOG, 'https') === 0) {
                $webhook_create_payload = array(
                    'url' => HTTPS_CATALOG . 'index.php?route=extension/module/cubit/webhook&autogen=true',
                    'event_types' => array(
                        ['name' => 'BILLING.SUBSCRIPTION.ACTIVATED'],
                        ['name' => 'BILLING.SUBSCRIPTION.CANCELLED'],
                        ['name' => 'BILLING.SUBSCRIPTION.SUSPENDED'],
                        ['name' => 'BILLING.SUBSCRIPTION.EXPIRED'],
                        ['name' => 'PAYMENT.SALE.COMPLETED'],
                        ['name' => 'PAYMENT.SALE.PENDING'],
                        ['name' => 'PAYMENT.SALE.DENIED'],
                        ['name' => 'PAYMENT.SALE.REFUNDED'],
                        ['name' => 'PAYMENT.SALE.REVERSED']
                    )
                );
    
                $webhook_create_request = $this->cubit->sendPaypalRequest('POST', $paypal_webhook_create_url, $access_token, array(), $webhook_create_payload);
    
                if ($webhook_create_request['status'] == 201) {
                    //Save webhook
                    $this->load->model('setting/setting');

                    if ($sandbox) {
                        $this->model_setting_setting->editSettingValue('module_cubit', 'module_cubit_paypal_sandbox_webhook_id', $webhook_create_request['body']['id']);
                    } else {
                        $this->model_setting_setting->editSettingValue('module_cubit', 'module_cubit_paypal_webhook_id', $webhook_create_request['body']['id']);
                    }

                    die(json_encode(array('webhook_id' => $webhook_create_request['body']['id'])));
                } else {
                    die(json_encode(array('error' =>$webhook_create_request['body']['message'])));
                }
            } else {
                die(json_encode(array('error' => $this->language->get('error_https'))));
            }
        } catch (Exception $e) {
            die(json_encode(array('error' => $e->getMessage())));
        }
    }

    public function listing() {
        if (!$this->user->hasPermission('access', 'extension/module/cubit')) {
            $this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->language('extension/module/cubit');
        $this->load->model('extension/module/cubit');

        $this->document->setTitle($this->language->get('heading_title_membership_listing'));

        $data = array();

        $filter_query = '';
        $filter = array();

        if (isset($this->request->get['filter_customer_fullname']) && $this->request->get['filter_customer_fullname']) {
            $data['filter_customer_fullname'] = $this->request->get['filter_customer_fullname'];
            $filter_query .= '&filter_customer_fullname=' . $this->request->get['filter_customer_fullname']; 
            $filter['customer_fullname'] = $this->request->get['filter_customer_fullname'];
        } else {
            $data['filter_customer_fullname'] = '';
        }

        if (isset($this->request->get['filter_cubit_offer_id']) && $this->request->get['filter_cubit_offer_id']) {
            $data['filter_cubit_offer_id'] = $this->request->get['filter_cubit_offer_id'];
            $filter_query .= '&filter_cubit_offer_id=' . $this->request->get['filter_cubit_offer_id'];
            $filter['cubit_offer_id'] = $this->request->get['filter_cubit_offer_id'];
        } else {
            $data['filter_cubit_offer_id'] = '';
        }

        if (isset($this->request->get['filter_status']) && $this->request->get['filter_status'] >= 0) {
            $data['filter_status'] = $this->request->get['filter_status'];
            $filter_query .= '&filter_status=' . $this->request->get['filter_status'];
            $filter['status'] = $this->request->get['filter_status'];
        } else {
            $data['filter_status'] = -1;
        }

        if (isset($this->request->get['filter_store_id'])) {
            $data['filter_store_id'] = $this->request->get['filter_store_id'];
            $filter_query .= '&filter_store_id=' . $this->request->get['filter_store_id'];
            $filter['store_id'] = $this->request->get['filter_store_id'];
        } else {
            $data['filter_store_id'] = -1;
        }

        if (isset($this->request->get['filter_paypal_subscription_id'])) {
            $data['filter_paypal_subscription_id'] = $this->request->get['filter_paypal_subscription_id'];
            $filter_query .= '&filter_paypal_subscription_id=' . $this->request->get['filter_paypal_subscription_id'];
            $filter['paypal_subscription_id'] = $this->request->get['filter_paypal_subscription_id'];
        } else {
            $data['filter_paypal_subscription_id'] = '';
        }

        if (isset($this->request->get['filter_paypal_subscription_status'])) {
            $data['filter_paypal_subscription_status'] = $this->request->get['filter_paypal_subscription_status'];
            $filter_query .= '&filter_paypal_subscription_status=' . $this->request->get['filter_paypal_subscription_status'];
            $filter['paypal_subscription_status'] = $this->request->get['filter_paypal_subscription_status'];
        } else {
            $data['filter_paypal_subscription_status'] = '';
        }

        $filter_query = trim($filter_query, '&');

        //Sorting
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
            
            if (isset($this->request->get['sort_order'])) {
                $sort_order = $this->request->get['sort_order'];
            } else {
                $sort_order = 'asc';
            }
        } else {
            $sort = 'date_added';
            $sort_order= 'desc';
        }

        if ($sort == 'customer_fullname' && $sort_order == 'asc') {
            $data['sort_customer_fullname'] = $this->url->link('extension/module/cubit/listing', '&user_token=' . $this->request->get['user_token'] . '&sort=customer_fullname&sort_order=desc&' . $filter_query);
        } else {
            $data['sort_customer_fullname'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=customer_fullname&sort_order=asc&' . $filter_query);
        }

        if ($sort == 'offer_id' && $sort_order == 'asc') {
            $data['sort_offer_id'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=offer_id&sort_order=desc&' . $filter_query);
        } else {
            $data['sort_offer_id'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=offer_id&sort_order=asc&' . $filter_query);
        }

        if ($sort == 'date_added' && $sort_order == 'asc') {
            $data['sort_date_added'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=date_added&sort_order=desc&' . $filter_query);
        } else {
            $data['sort_date_added'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=date_added&sort_order=asc&' . $filter_query);
        }

        if ($sort == 'date_ends' && $sort_order == 'asc') {
            $data['sort_date_ends'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=date_ends&sort_order=desc&' . $filter_query);
        } else {
            $data['sort_date_ends'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=date_ends&sort_order=asc&' . $filter_query);
        }

        if ($sort == 'status' && $sort_order == 'asc') {
            $data['sort_status'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=status&sort_order=desc&' . $filter_query);
        } else {
            $data['sort_status'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&sort=status&sort_order=asc&' . $filter_query);
        }

        $data['sort'] = $sort;
        $data['sort_order'] = $sort_order;
        
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['memberships'] = array();

        $filter = array_merge($filter, array(
            'sort' => $sort,
            'sort_order' => $sort_order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        ));

        $memberships = $this->model_extension_module_cubit->getMemberships($filter);

        $this->load->model('customer/customer');

        foreach ($memberships as $membership) {
            $date_added = new DateTime($membership['date_added'], new DateTimeZone('UTC'));

            $paypal_subscription_create_time = new DateTime($membership['paypal_subscription_create_time'], new DateTimeZone('UTC'));
            $paypal_subscription_update_time = new DateTime($membership['paypal_subscription_update_time'], new DateTimeZone('UTC'));
            
            $date_added->setTimeZone(new DateTimeZone(date_default_timezone_get()));

            $paypal_subscription_create_time->setTimeZone(new DateTimeZone(date_default_timezone_get()));
            $paypal_subscription_update_time->setTimeZone(new DateTimeZone(date_default_timezone_get()));

            if ($membership['date_ends'] != '0000-00-00 00:00:00') {
                $date_ends = new DateTime($membership['date_ends'], new DateTimeZone('UTC'));
                $date_ends->setTimeZone(new DateTimeZone(date_default_timezone_get()));

                $membership_date_ends = $date_ends->format('Y-m-d H:i');
            } else {
                $membership_date_ends = '-';
            }

            $membership_info = array(
                'cubit_membership_id' => $membership['cubit_membership_id'],
                'offer' => $membership['offer'],
                'subscription' => array(
                    'id' => $membership['paypal_subscription_id'],
                    'approve_link' => $membership['paypal_subscription_approve_link'],
                    'create_time' => $paypal_subscription_create_time->format('Y-m-d H:i:s'),
                    'update_time' => $paypal_subscription_update_time->format('Y-m-d H:i:s'),
                    'status' => $membership['paypal_subscription_status']
                ),
                'customer' => array(
                    'customer_id' => $membership['customer_id'],
                    'fullname' => $membership['customer_fullname'],
                    'link' => $this->url->link('customer/customer/edit', 'user_token=' . $this->request->get['user_token'] . '&customer_id=' . $membership['customer_id'])
                ),
                'date_added' => $date_added->format('Y-m-d H:i'),
                'date_ends' => $membership_date_ends,
                'view' => $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $membership['cubit_membership_id']),
                'status' =>  $membership['status']
            );

            $data['memberships'][] = $membership_info;
        }

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }

        $data['heading_title'] = $this->language->get('heading_title_membership_listing');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->request->get['user_token']),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_memberships'),
            'href' => $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token']),
        );

        $data['user_token'] = $this->request->get['user_token'];

        $data['cubit_offers'] = $this->model_extension_module_cubit->getOffers(array('status' => 1));

        $this->load->model('setting/store');

        $derivative_stores = $this->model_setting_store->getStores();

        $data['stores'] = array_merge(
            array(['store_id' => 0, 'name' => $this->config->get('config_name')]),
            $derivative_stores
        );
        
        $data['sync'] = HTTP_CATALOG . 'index.php?route=extension/module/cubit/cron';
        $data['settings'] = $this->url->link('extension/module/cubit/index', 'user_token=' . $this->request->get['user_token']);

        $pagination = new Pagination();

        $pagination->total = $this->model_extension_module_cubit->getTotalMemberships($filter);
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'] . '&page={page}&sort=' . $sort . '&sort_order=' .$sort_order . '&' . $filter_query );

        $data['pagination']  = $pagination->render();

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/cubit_listing', $data));
    }

    public function membership() {
        if (!$this->user->hasPermission('access', 'extension/module/cubit')) {
            $this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
        }

        if (!isset($this->request->get['cubit_membership_id'])) {
            $this->response->redirect($this->url->link('error/not_found', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->model('extension/module/cubit');

        $membership = $this->model_extension_module_cubit->getMembership($this->request->get['cubit_membership_id']);

        if ($membership) {
            $this->load->language('extension/module/cubit');

            $this->document->setTitle(sprintf($this->language->get('heading_title_membership'), $membership['paypal_subscription_id']));

            $data = array();

            $data['cubit_membership_id'] = $membership['cubit_membership_id'];

            $data['offer'] = $membership['offer'];
            $data['billing_plan'] =  sprintf($this->language->get('text_billing_plan'), $this->currency->format($membership['amount'], $membership['currency'], '', true), $membership['frequency_days']);

            $data['customer'] = array(
                'customer_id' => $membership['customer_id'],
                'fullname' => $membership['customer_fullname'],
                'link' =>  $this->url->link('customer/customer/edit', 'customer_id=' . $membership['customer_id'])
            ); 

            $date_added = new DateTime($membership['date_added'], new DateTimeZone('UTC'));

            $paypal_subscription_create_time = new DateTime($membership['paypal_subscription_create_time'], new DateTimeZone('UTC'));
            $paypal_subscription_update_time = new DateTime($membership['paypal_subscription_update_time'], new DateTimeZone('UTC'));

            $date_added->setTimezone(new DateTimeZone(date_default_timezone_get()));
            
            $paypal_subscription_create_time->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $paypal_subscription_update_time->setTimezone(new DateTimeZone(date_default_timezone_get()));

            $data['subscription'] = array(
                'id' => $membership['paypal_subscription_id'],
                'plan' => $membership['paypal_subscription_plan'],
                'create_time' => $paypal_subscription_create_time->format('Y-m-d H:i:s'),
                'update_time' => $paypal_subscription_update_time->format('Y-m-d H:i:s'),
                'status' => $membership['paypal_subscription_status']
            );

            $data['date_added'] = $date_added->format('Y-m-d H:i');

            if ($membership['date_renewed'] != '0000-00-00 00:00:00') {
                $date_renewed = new DateTime($membership['date_renewed'], new DateTimeZone('UTC'));
                $date_renewed->setTimezone(new DateTimeZone(date_default_timezone_get()));

                $data['date_renewed'] = $date_renewed->format('Y-m-d H:i');

            } else {
                $data['date_renewed'] = $membership['date_renewed'];
            }

            if ($membership['date_ends'] != '0000-00-00 00:00:00') {
                $date_ends = new DateTime($membership['date_ends'], new DateTimeZone('UTC'));
                $date_ends->setTimezone(new DateTimeZone(date_default_timezone_get()));
                
                $data['date_ends'] = $date_ends->format('Y-m-d H:i');
            } else {
                $data['date_ends'] = $membership['date_ends'];
            }

            if (in_array($membership['paypal_subscription_status'], array(PAYPAL_SUBSCRIPTION_STATUS_SUSPENDED, PAYPAL_SUBSCRIPTION_STATUS_ACTIVE))) {
                $data['subscription']['cancel'] = $this->url->link('extension/module/cubit/cancel', 'user_token=' . $this->request->get['user_token']);
            }

            $pagination = new Pagination();

            if (isset($this->request->get['page'])) {
                $page = $this->request->get['page'];
            } else {
                $page = 1;
            }

        //Sorting
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
            
            if (isset($this->request->get['sort_order'])) {
                $sort_order = $this->request->get['sort_order'];
            } else {
                $sort_order = 'asc';
            }
        } else {
            $sort = 'update_time';
            $sort_order= 'desc';
        }

        if ($sort == 'amount' && $sort_order == 'asc') {
            $data['sort_amount'] = $this->url->link('extension/module/cubit/membership', '&user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=amount&sort_order=desc#transactions');
        } else {
            $data['sort_amount'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=amount&sort_order=asc#transactions');
        }

        if ($sort == 'create_time' && $sort_order == 'asc') {
            $data['sort_create_time'] = $this->url->link('extension/module/cubit/membership', '&user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=create_time&sort_order=desc#transactions');
        } else {
            $data['sort_create_time'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=create_time&sort_order=asc#transactions');
        }

        if ($sort == 'update_time' && $sort_order == 'asc') {
            $data['sort_update_time'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=update_time&sort_order=desc#transactions');
        } else {
            $data['sort_update_time'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=update_time&sort_order=asc#transactions');
        }

        if ($sort == 'state' && $sort_order == 'asc') {
            $data['sort_state'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=state&sort_order=desc#transactions');
        } else {
            $data['sort_state'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=state&sort_order=asc#transactions');
        }

        if ($sort == 'resource_type' && $sort_order == 'asc') {
            $data['sort_resource_type'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=resource_type&sort_order=desc#transactions');
        } else {
            $data['sort_resource_type'] = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $this->request->get['cubit_membership_id'] . '&sort=resource_type&sort_order=asc#transactions');
        }

        $data['sort'] = $sort;
        $data['sort_order'] = $sort_order;

            $data['transactions'] = $this->model_extension_module_cubit->getPaypalTransactions(array('subscription' => $membership['paypal_subscription_id'], 'sort' => $sort, 'sort_order' => $sort_order, 'start' => ($page - 1) * 5, 'limit' => 5));
     
            $pagination->page = $page;
            $pagination->limit = 5;
            $pagination->total = $this->model_extension_module_cubit->getTotalPaypalTransactions(array('subscription' => $membership['paypal_subscription_id']));
            $pagination->url = $this->url->link('extension/module/cubit/membership', 'user_token=' . $this->request->get['user_token'] . '&cubit_membership_id=' . $membership['cubit_membership_id'] . '&sort=' . $sort . '&sort_order=' . $sort_order . '&page={page}#transactions');

            $data['transaction_pagination'] = $pagination->render();

            $data['status']  = $membership['status'];

            $data['breadcrumbs'] = array(
                [   
                    'href' => $this->url->link('common/dashboard', 'user_token=' . $this->request->get['user_token'], true),
                    'text' => $this->language->get('text_home')
                ],
                [   
                    'href' => $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token'], true),
                    'text' => $this->language->get('text_memberships')
                ],
            );

            $data['return'] = $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token']);

            if (isset($this->session->data['error'])) {
                $data['error_warning'] = $this->session->data['error'];
                unset($this->session->data['error']);
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = $this->session->data['success'];
                unset($this->session->data['success']);
            }

            $data['heading_title'] = sprintf($this->language->get('heading_title_membership'), $membership['paypal_subscription_id']);
            $data['user_token'] = $this->request->get['user_token'];

            $data['header'] = $this->load->controller('common/header');
            $data['footer'] = $this->load->controller('common/footer');
            $data['column_left'] = $this->load->controller('common/column_left');

            $this->response->setOutput($this->load->view('extension/module/cubit_membership', $data));
        } else {
            $this->response->redirect($this->url->link('error/not_found', 'user_token=' . $this->session->data['user_token'], true));
        }
    }

    public function cancel() {
        header('Content-type: application/json');

        $this->load->language('extension/module/cubit');

        if (!$this->user->hasPermission('modify', 'extension/module/cubit')) {
            die(json_encode(array('error' => $this->language->get('error_persmission'))));
        }

        if (!isset($this->request->post['paypal_subscription_id'])) {
            die(json_encode(array('error' => $this->language->get('error_request'))));
        }

        $this->load->model('extension/module/cubit');

        $subscription = $this->model_extension_module_cubit->getPaypalSubscription($this->request->post['paypal_subscription_id']);

        if ($subscription) {
            $paypal_client_id = $this->config->get('module_cubit_paypal_client_id');
            $paypal_secret = $this->config->get('module_cubit_paypal_secret');

            try {
                $access_token = $this->cubit->getPaypalAccessToken($paypal_client_id, $paypal_secret, $this->config->get('module_cubit_paypal_sandbox'));

                $paypal_subscription_cancel_url = '';

                if ($this->config->get('module_cubit_paypal_sandbox')) {
                    $paypal_subscription_cancel_url = 'https://api.sandbox.paypal.com/v1/billing/subscriptions/' . $subscription['id'] . '/cancel';
                } else {
                    $paypal_subscription_cancel_url = 'https://api.paypal.com/v1/billing/subscriptions/' . $subscription['id'] . '/cancel';
                }

                $reason = '';

                if (isset($this->request->post['reason'])) {
                    $reason = $this->request->post['reason'];
                } else {
                    $reason = $this->language->get('text_reason_cancel');
                }

                $subscription_cancel_payload = array(
                    'reason' => $reason
                );

                $subscription_cancel_request = $this->cubit->sendPaypalRequest('POST', $paypal_subscription_cancel_url , $access_token, array(), $subscription_cancel_payload);

                if ($subscription_cancel_request['status'] == 204) {
                    $subscription['update_time'] = gmdate('Y-m-d H:i:s');
                    $subscription['status'] = PAYPAL_SUBSCRIPTION_STATUS_CANCELLED;

                    $this->model_extension_module_cubit->editPaypalSubscription($subscription['id'], $subscription);

                    $this->session->data['success'] = $this->language->get('text_success_membreship_cancel');

                    die(json_encode(array('success' => true)));
                } else {
                    die(json_encode(array('error' => $subscription_cancel_request['body']['message'] . ' (STATUS: ' . $subscription_cancel_request['status'] . ')')));
                }
            } catch (Exception $e) {
                die(json_encode(array('error' => $e->getMessage())));
            }
        } else {
            die(json_encode(array('error' => $this->language->get('error_subscription'))));
        }
    }

    public function deleteMembership() {
        header("Content-type: applicaiton/json");

        $this->load->language('extension/module/cubit');

        if (!isset($this->request->post['cubit_membership_id'])) {
            die(json_encode(array('error' => $this->language->get('error_params'))));
        }

        $this->load->model('extension/module/cubit');

        $membership = $this->model_extension_module_cubit->getMembership($this->request->post['cubit_membership_id']);

        if ($membership) {
           $membership = $this->model_extension_module_cubit->deleteMembership($this->request->post['cubit_membership_id']);

            $this->session->data['success'] = $this->language->get('text_success_membership_delete');

            $payload = array(
                'redirect' => $this->url->link('extension/module/cubit/listing', 'user_token=' . $this->request->get['user_token']),
                'success' => true
            );

            die(json_encode($payload));
        } else {
            die(json_encode(array('error' => $this->language->get('error_membership'))));
        }
    }

    public function debug() {
        $this->load->model('extension/module/cubit');

        $this->model_extension_module_cubit->deleteMembership($this->request->get['cubit_membership_id']);
    }

    public function deleteOffer() {
        header('Content-type: application/json');

        $this->load->language('extension/module/cubit');

        if (!$this->user->hasPermission('modify', 'extension/module/cubit')) {
            die(json_encode(array('error' => $this->language->get('error_persmission'))));
        }

        if (isset($this->request->post['cubit_offer_id'])) {
            $this->load->model('extension/module/cubit');

            $this->model_extension_module_cubit->deleteOffer($this->request->post['cubit_offer_id']);

            $result = array(
                'success' => true
            );
        } else {
            $result = array(
                'error' => $this->language->get('error_delete')
            );
        }

        $this->response->setOutput(json_encode($result));
    }

    public function install() {
        $this->uninstall();

        $this->db->query("CREATE TABLE " . DB_PREFIX  . "cubit_offer (
            `cubit_offer_id` INT NOT NULL AUTO_INCREMENT,
            `amount` DECIMAL(10,2) NOT NULL,
            `frequency_days` INT NOT NULL,
            `active_customer_group_id` INT NOT NULL,
            `expire_customer_group_id` INT NOT NULL,
            `sort_order` INT NULL DEFAULT 0,
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`cubit_offer_id`))"
        );

        $default_customer_group_id = $this->config->get('config_customer_group_id');
        
        $this->db->query("INSERT INTO " . DB_PREFIX . "cubit_offer(amount, frequency_days, active_customer_group_id, expire_customer_group_id, sort_order, status) VALUES (1.99, 30, " . $default_customer_group_id . ", " . $default_customer_group_id . ", 0, 1);");

        $this->db->query("CREATE TABLE " . DB_PREFIX . "cubit_offer_description (
            `name` VARCHAR(255) NOT NULL,
            `cubit_offer_id` INT NOT NULL,
            `language_id` INT NOT NULL,
            PRIMARY KEY (`cubit_offer_id`, `language_id`))"
        );

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "cubit_offer_description VALUES('GOLD MEMBER', 1, ". $language['language_id'] . ");");
        }

        $this->db->query("CREATE TABLE " . DB_PREFIX . "cubit_membership (
            `cubit_membership_id` INT NOT NULL AUTO_INCREMENT,
            `customer_id` INT NOT NULL,
            `store_id` INT NOT NULL DEFAULT 0,
            `offer` VARCHAR(255),
            `offer_id` INT NOT NULL,
            `paypal_subscription_id` VARCHAR(100) NOT NULL DEFAULT '',
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(3),
            `frequency_days` INT,
            `active_customer_group_id` INT NOT NULL,
            `expire_customer_group_id` INT NOT NULL,
            `date_added` DATETIME NOT NULL DEFAULT NOW(),
            `date_renewed` DATETIME NULL,
            `date_ends` DATETIME NULL,
            `display` TINYINT NOT NULL DEFAULT 1,
            `status` TINYINT NOT NULL DEFAULT 0,
            KEY (`customer_id`),
            KEY (`paypal_subscription_id`),
            PRIMARY KEY (`cubit_membership_id`)
            )"
        );
        
        $this->db->query("CREATE TABLE " . DB_PREFIX . "cubit_paypal_subscription (
            `id` VARCHAR(100) NOT NULL,
            `plan` VARCHAR(100) NOT NULL,
            `product` VARCHAR(100) NOT NULL,
            `approve_link` VARCHAR(255),
            `create_time` DATETIME,
            `update_time` DATETIME,
            `status` VARCHAR(100) NOT NULL,
            KEY (`id`)
            )"
        );

        $this->db->query('CREATE TABLE ' . DB_PREFIX . 'cubit_paypal_transaction (
            `id` VARCHAR(45) NOT NULL,
            `subscription` VARCHAR(45) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(3),
            `create_time` DATETIME,
            `update_time` DATETIME,
            `resource_type` VARCHAR(45),
            `state` VARCHAR(45) NOT NULL,
            KEY (`subscription`))'
        );

        //Add admin menu
        $this->load->model('setting/event');

        $this->model_setting_event->addEvent('module_cubit_memberships_admin', 'admin/view/common/column_left/before', 'extension/module/cubit/beforeColumnLeft');
    
        //Add sale point
        $this->load->model('design/layout');

        $layout_id = $this->model_design_layout->addLayout(array(
            'name' => 'Cubit Salepoint',
            'layout_route' => array(
                ['store_id' => 0, 'route' => 'extension/module/cubit/salepoint']
            ),
            'layout_module'=> array(
                ['position' => 'content_bottom', 'code' => 'cubit', 'sort_order' => 0]
            )
        ));

        $this->load->model('setting/setting');

        $this->model_setting_setting->editSetting('module_cubit', array(
            'module_cubit_salepoint_layout_id' => $layout_id
        ));
    }

    public function uninstall() {
        $this->db->query('DROP TABLE IF EXISTS `' .  DB_PREFIX . 'cubit_membership`');
        $this->db->query('DROP TABLE IF EXISTS `' .  DB_PREFIX . 'cubit_offer`');
        $this->db->query('DROP TABLE IF EXISTS `' .  DB_PREFIX . 'cubit_offer_description`');
        $this->db->query('DROP TABLE IF EXISTS `' .  DB_PREFIX . 'cubit_paypal_subscription`');
        $this->db->query('DROP TABLE IF EXISTS `' .  DB_PREFIX . 'cubit_paypal_transaction`');

        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('module_cubit_memberships_admin');

        if ($this->config->get('module_cubit_salepoint_layout_id')) {
            $this->load->model('design/layout');

            $this->model_design_layout->deleteLayout($this->config->get('module_cubit_salepoint_layout_id'));
        }
    }

    public function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/cubit')) {
            $this->error = $this->language->get('error_permission');
        }
        
        if (!isset($this->request->post['module_cubit_paypal_client_id']) || !$this->request->post['module_cubit_paypal_client_id']) {
            $this->errors['paypal_client_id'] = $this->language->get('error_required');
        }

        if (!isset($this->request->post['module_cubit_paypal_secret']) || !$this->request->post['module_cubit_paypal_secret']) {
            $this->errors['paypal_secret'] = $this->language->get('error_required');
        }

        if (!isset($this->request->post['module_cubit_payment_failure_threshold']) || $this->request->post['module_cubit_payment_failure_threshold'] > 999 || $this->request->post['module_cubit_payment_failure_threshold'] < 0) {
            $this->errors['payment_failure_threshold'] = $this->language->get('error_payment_failure_threshold');
        }

        if (isset($this->request->post['module_cubit_offer'])) {
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($this->request->post['module_cubit_offer'] as $cubit_offer_id => $offer) {
                foreach ($languages as $language) {
                    if (!isset($offer['name'][$language['language_id']]) || !$offer['name'][$language['language_id']]) {
                        $this->errors['offer'][$cubit_offer_id]['name'][$language['language_id']] = $this->language->get('error_required');
                    }
                }

                if ($offer['active_customer_group_id'] == $offer['expire_customer_group_id']) {
                    $this->errors['offer'][$cubit_offer_id]['expire_customer_group_id'] = $this->language->get('error_expire_customer_group_id');
                }

                if (!is_numeric($offer['amount']) || $offer['amount'] <= 0) {
                    $this->errors['offer'][$cubit_offer_id]['amount'] = $this->language->get('error_offer_amount');
                }

                if (!is_numeric($offer['frequency_days']) || $offer['frequency_days'] < 1) {
                    $this->errors['offer'][$cubit_offer_id]['frequency_days'] = $this->language->get('error_frequency_days');
                }
            }
        }

        return !$this->errors;
    }

    public function updateMembership() {
        header("Content-type: application/json");

        $this->load->language('extension/module/cubit');

        if (!$this->user->hasPermission('modify', 'extension/module/cubit')) {
            die(json_encode(array('error' => $this->language->get('error_permission'))));
        }

        if (!isset($this->request->post['cubit_membership_id'])) {
            die(json_encode(array('error' => $this->language->get('error_params'))));
        }

        $this->load->model('extension/module/cubit');

        $membership = $this->model_extension_module_cubit->getMembership($this->request->post['cubit_membership_id']);

        if ($membership) {
            if (isset($this->request->post['date_ends']) && !DateTime::createFromFormat('Y-m-d H:i:s', $this->request->post['date_ends'])) {
                die(json_encode(array('error' => $this->language->get('error_date'))));
            } else if (isset($this->request->post['date_ends'])) {
                $membership['date_ends'] = gmdate('Y-m-d H:i:s', strtotime($this->request->post['date_ends']));
            }

            if (isset($this->request->post['status'])) {
                $membership = (bool)$this->request->post['status'];
            }

            $this->model_extension_module_cubit->editMembership($membership['cubit_membership_id'], $membership);

            die(json_encode(array('success' => true)));
        } else {
            die(json_encode(array('error' => $this->language->get('error_membership'))));
        }
    }

    public function beforeColumnLeft(&$route, &$data) {
        if ($this->config->get('module_cubit_status')) {
            $this->load->language('extension/module/cubit');

            $sale_index = array_search('menu-customer', array_column($data['menus'], 'id'));
    
            $data['menus'][$sale_index]['children'][] = array (
                'cubit-membership',
                'name' => $this->language->get('text_memberships'),
                'href' => $this->url->link('extension/module/cubit/listing', '&user_token=' .$this->request->get['user_token']),
                'icon' => 'fa fa-puzzle-piece fw'
            );
        }
    }
}