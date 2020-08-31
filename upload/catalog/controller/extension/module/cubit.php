<?php

define('PAYPAL_SUBSCRIPTION_STATUS_APPROVAL_PENDING', 'APPROVAL_PENDING');
define('PAYPAL_SUBSCRIPTION_STATUS_APPROVED', 'APPROVED');
define('PAYPAL_SUBSCRIPTION_STATUS_ACTIVE', 'ACTIVE');
define('PAYPAL_SUBSCRIPTION_STATUS_SUSPENDED', 'SUSPENDED');
define('PAYPAL_SUBSCRIPTION_STATUS_CANCELLED', 'CANCELLED');
define('PAYPAL_SUBSCRIPTION_STATUS_EXPIRED', 'EXPIRED');

function webhookRespond( $status, $message) {
    header("HTTP/1.1 " . $status . " " . $message);

    die($status . " " . $message);
}

class ControllerExtensionModuleCubit extends Controller {
    private $error = array();

    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->library('cubit');

        $this->registry->set('cubit', new Cubit($this->registry));
    }

    public function index() {
        $this->load->language('extension/module/cubit');

        if (!$this->customer->isLogged()) {
            return sprintf($this->language->get('error_login'), $this->url->link('account/login'));
        }

        $this->load->model('extension/module/cubit');

        $membership = $this->cubit->getMembership();

        if ($membership && $membership['status']) {
            return $this->language->get('error_membership_exist');
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && $this->validateMembershipOrder()) {
            $offer = $this->model_extension_module_cubit->getOffer($this->request->post['membership_offer']);

            if (!$offer || !$offer['status']) {
                $this->session->data['error'] = $this->language->get('error_offer');

                $this->response->redirect($this->url->link('extension/module/cubit/salepoint', '', $this->config->get('config_secure')));
            }

            $billing_plan_currency_code = '';
            $billing_plan_amount_value = '';

            if ($this->config->get('module_cubit_currency_convert') && $this->config->get('config_currency') != $this->session->data['currency']) {
                $billing_plan_amount_value = number_format($this->currency->convert($offer['amount'], $this->config->get('config_currency'), $this->session->data['currency']), 2, '.', '');
                $billing_plan_currency_code = $this->session->data['currency'];
            } else {
                $billing_plan_amount_value = number_format($offer['amount'], 2, '.', '');
                $billing_plan_currency_code = $this->config->get('config_currency');
            }

            $memberships = $this->model_extension_module_cubit->getMemberships(array('customer_id' => $this->customer->getId()));

            if ($memberships) {
                $paypal_client_id = $this->config->get('module_cubit_paypal_client_id');
                $paypal_secret = $this->config->get('module_cubit_paypal_secret');

                try {
                    $access_token = $this->cubit->getPaypalAccessToken($paypal_client_id, $paypal_secret, $this->config->get('module_cubit_paypal_sandbox'));
                                
                    //Cancel old membership subscriptions
                    foreach ($memberships as $old_membership) {
                        $subscription = $this->model_extension_module_cubit->getPaypalSubscription($old_membership['paypal_subscription_id']);

                        if ($subscription && $subscription['status'] != PAYPAL_SUBSCRIPTION_STATUS_CANCELLED) {
                            $paypal_subscription_cancel_url = '';
            
                            if ($this->config->get('module_cubit_paypal_sandbox')) {
                                $paypal_subscription_cancel_url = 'https://api.sandbox.paypal.com/v1/billing/subscriptions/' . $subscription['id'] . '/cancel';
                            } else {
                                $paypal_subscription_cancel_url = 'https://api.paypal.com/v1/billing/subscriptions/' . $subscription['id'] . '/cancel';
                            }
            
                            $subscription_cancel_payload = array(
                                'reason' => $this->language->get('text_reason_user_cancel')
                            );
            
                            $this->cubit->sendPaypalRequest('POST', $paypal_subscription_cancel_url, $access_token, array(), $subscription_cancel_payload);
                        }
                    }
                } catch (Exception $e) {
                    if ($this->config->get('config_error_display')) {
                        die('CUBIT ERROR: ' . $e->getMessage());
                    } else {
                        die('CUBIT ERROR');
                    }
    
                    if ($this->config->get('config_error_log')) {
                        $this->log->write('CUBIT ERROR: ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
                    }
                }
            }

            $date_added = gmdate('Y-m-d H:i:s', time());

            //Create membership
            $membership = array(
                'customer_id' => $this->customer->getId(),
                'offer' => $offer['name'],
                'offer_id' => $offer['cubit_offer_id'],
                'amount' => $billing_plan_amount_value,
                'currency' => $billing_plan_currency_code,
                'paypal_subscription_id' => '',
                'frequency_days' => $offer['frequency_days'],
                'active_customer_group_id' => $offer['active_customer_group_id'],
                'expire_customer_group_id' => $offer['expire_customer_group_id'],
                'store_id' => $this->config->get('config_store_id'),
                'date_added' => $date_added,
                'date_renewed' => null,
                'date_ends' => null,
                'status' => 0
            );

            //Hide old memberships, but keep the records.
            $this->model_extension_module_cubit->customerMembershipsDisplayOff($this->customer->getId());

            //Add new
            $cubit_membership_id = $this->model_extension_module_cubit->addMembership($membership);

            $paypal_client_id = $this->config->get('module_cubit_paypal_client_id');
            $paypal_secret = $this->config->get('module_cubit_paypal_secret');

            try {
                $access_token = $this->cubit->getPaypalAccessToken($paypal_client_id, $paypal_secret, $this->config->get('module_cubit_paypal_sandbox'));

                $paypal_product_create_url = '';

                if ($this->config->get('module_cubit_paypal_sandbox')) {
                    $paypal_product_create_url = 'https://api.sandbox.paypal.com/v1/catalogs/products';
                } else {
                    $paypal_product_create_url = 'https://api.paypal.com/v1/catalogs/products';
                }

                $product_create_payload = array(
					'name' => sprintf($this->language->get('text_membership_offer'), $cubit_membership_id),
					'description' => $this->language->get('text_membership_offer_description'),
					'type' => 'DIGITAL',
					'category' => 'OTHER',
				);

                $product_create_request = $this->cubit->sendPaypalRequest('POST', $paypal_product_create_url, $access_token, array(), $product_create_payload);

                if ($product_create_request['status'] == 201) {
                    $paypal_product_reference = $product_create_request['body']['id'];
                } else {
                    die('CUBIT ERROR (PAYPAL): ' . $product_create_request['body']['message']);
                }

                $paypal_plan_create_url = '';

                if ($this->config->get('module_cubit_paypal_sandbox')) {
                    $paypal_plan_create_url = 'https://api.sandbox.paypal.com/v1/billing/plans';
                } else {
                    $paypal_plan_create_url = 'https://api.paypal.com/v1/billing/plans';
                }

                $paypal_billing_cycles = array(
					[
						'frequency' => [
							'interval_unit' => 'DAY',
							'interval_count' => $offer['frequency_days'],
						],
						'sequence' => 1,
						'tenure_type' => 'REGULAR',
						'total_cycles' => 0,
						'pricing_scheme' => [
							'fixed_price' => [
								'value' => $billing_plan_amount_value,
								'currency_code' => $billing_plan_currency_code ,
							]
						]
					]
				);

                $plan_create_payload = array(
					'product_id' => $paypal_product_reference,
					'name' => $offer['name'],
					'description' => sprintf($this->language->get('text_billing_plan'), $this->currency->format($billing_plan_amount_value, $billing_plan_currency_code, 1, true), $offer['frequency_days']),
					'billing_cycles' => $paypal_billing_cycles,
					'payment_preferences' => [
						'auto_bill_outstanding' => true,
						'setup_fee' => [
							'value' => $billing_plan_amount_value,
							'currency_code' => $billing_plan_currency_code,
						],
						"setup_fee_failure_action" => "CANCEL",
						"payment_failure_threshold" => $this->config->get('module_cubit_payment_failure_threshold') ? $this->config->get('module_cubit_payment_failure_threshold') : 1
					]
				);

                $plan_create_request = $this->cubit->sendPaypalRequest('POST', $paypal_plan_create_url, $access_token, array(), $plan_create_payload);

                if ($plan_create_request['status'] == 201) {
                    $paypal_plan_reference = $plan_create_request['body']['id'];
                } else {
                    die('CUBIT ERROR (PAYPAL PLAN): '. $plan_create_request['body']['message']);
                }

                $language = $this->model_localisation_language->getLanguage($this->config->get('config_language_id'));

                preg_match('/[a-z]{2}[-|_][A-Z]{2}/', $language['locale'], $matches, PREG_OFFSET_CAPTURE, 0);

                if ($matches) {
                    $locale = str_replace('_', '-', $matches[0][0]);
                } else {
                    $locale = 'en-US';
                }

                $subscriber = [
                    "name" => [
                        "full_name" => $this->customer->getFirstname() . ' ' . $this->customer->getLastname()
                    ],
                    "email_address" => $this->customer->getEmail(),
				];

                $application_context = array(
					"brand_name" => $this->config->get('config_name'),
					"locale" => $locale,
					"shipping_preference" => 'NO_SHIPPING',
					"user_action" => "SUBSCRIBE_NOW",
					"payment_method" => [
						"payer_selected" => "PAYPAL",
						"payee_preferred" => "UNRESTRICTED",
					],
                    "return_url" => $this->url->link('extension/module/cubit/success', '', $this->config->get('config_secure')),
					"cancel_url" => $this->url->link('extension/module/cubit/refuse', '', $this->config->get('config_secure'))
				);

                $start_time = gmdate(DateTime::ATOM, strtotime("+" . $offer['frequency_days'] . " days"));

                $subscription_create_payload = array(
					"plan_id" => $paypal_plan_reference,
					"start_time" => $start_time,
					"subscriber" => $subscriber,
					"application_context" => $application_context
                );            

                $paypal_subscription_create_url = '';

                if ($this->config->get('module_cubit_paypal_sandbox')) {
                    $paypal_subscription_create_url = 'https://api.sandbox.paypal.com/v1/billing/subscriptions';
                } else {
                    $paypal_subscription_create_url = 'https://api.paypal.com/v1/billing/subscriptions';
                }

                $subscription_create_request = $this->cubit->sendPaypalRequest('POST', $paypal_subscription_create_url, $access_token, array(), $subscription_create_payload);

                if ($subscription_create_request['status'] == 201) {
                    $approve_link_index = array_search('approve', array_column($subscription_create_request['body']['links'], 'rel'));
                    $approve_link = $subscription_create_request['body']['links'][$approve_link_index]['href'];

                    $subscription = array(
                        'id' => $subscription_create_request['body']['id'],
                        'plan' =>  $paypal_plan_reference,
                        'product' =>  $paypal_product_reference,
						'approve_link' => $approve_link,
						'create_time' => gmdate('Y-m-d H:i:s', strtotime($subscription_create_request['body']['create_time'])),
						'update_time' => gmdate('Y-m-d H:i:s', strtotime($subscription_create_request['body']['create_time'])),
						'status' => PAYPAL_SUBSCRIPTION_STATUS_APPROVAL_PENDING,
                    );
                    
                    $this->model_extension_module_cubit->addPaypalSubscription($subscription);

                    $membership['paypal_subscription_id'] = $subscription_create_request['body']['id'];

                    $this->model_extension_module_cubit->editMembership($cubit_membership_id, $membership);

                    $this->response->redirect($approve_link);
                } else {
                    die('CUBIT ERROR (PAYPAL SUBSCRIPTION)' . ': ' . $subscription_create_request['body']['message']);
                }
            } catch (Exception $e) {
                if ($this->config->get('config_error_display')) {
                    die('CUBIT ERROR: ' . $e->getMessage());
                } else {
                    die('CUBIT ERROR');
                }

                if ($this->config->get('config_error_log')) {
                    $this->log->write('CUBIT ERROR: ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
                }
            }
        }

        if ($this->error) {
            $data['error_warning'] = $this->language->get('error_membership_form');
        }

        if (isset($this->errors['membership_offer'])) {
            $data['membership_offer_error'] = $this->errors['membership_offer'];
        } else {
            $data['membership_offer_error'] = '';
        }

        if (isset($this->errors['agree'])) {
            $data['agree_error'] = $this->errors['agree'];
        } else {
            $data['agree_error'] = '';
        }

        $this->load->model('extension/module/cubit');

        $offers = $this->model_extension_module_cubit->getOffers(array('status' => 1));

        function sortByOrder($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        }

        usort($offers, 'sortByOrder');

        $data['offer'] = array();

        foreach ($offers as $offer) {
            $billing_plan_currency_code = '';
            $billing_plan_amount_value = '';

            if ($this->config->get('module_cubit_currency_convert') && $this->config->get('config_currency') != $this->session->data['currency']) {
                $billing_plan_amount_value = number_format($this->currency->convert($offer['amount'], $this->config->get('config_currency'), $this->session->data['currency']), 2, '.', '');
                $billing_plan_currency_code = $this->session->data['currency'];
            } else {
                $billing_plan_amount_value = number_format($offer['amount'], 2, '.', '');
                $billing_plan_currency_code = $this->config->get('config_currency');
            }

            $offer['billing_plan'] = sprintf(
				$this->language->get('text_billing_plan'),
				$this->currency->format($billing_plan_amount_value, $billing_plan_currency_code, 1, true),
				$offer['frequency_days']
			);

            $data['offers'][] = $offer;
        }

        if ($this->config->get('module_cubit_terms_option') != 'disabled') {
            $terms = $this->config->get('module_cubit_terms');

            if (isset($terms[$this->config->get('config_language_id')])) {
                $data['terms_option'] = $this->config->get('module_cubit_terms_option');
                $data['terms'] = htmlspecialchars($terms[$this->config->get('config_language_id')]);
            }
        }

        $store_url = '';

        if ($this->config->get('config_secure')) {
            $store_url = $this->config->get('config_ssl') ? $this->config->get('config_ssl') : $this->config->get('config_url');
        } else {
            $store_url = $this->config->get('config_url');
        }

        $data['action'] = $store_url .  ltrim($this->request->server['REQUEST_URI'], '/');

        return $this->load->view('extension/module/cubit', $data);
    }

    public function validateMembershipOrder() {
        if (!isset($this->request->post['membership_offer'])) {
            $this->error['membership_offer'] = $this->language->get('error_membership_offer');
        }

        if ($this->config->get('module_cubit_terms_option') == 'required' && !$this->request->post['agree']) {
            $this->error['agree'] = $this->language->get('error_terms');
        }

        return !$this->error;
    }

    public function success() {
        if (!$this->config->get('module_cubit_status')) {
            $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        $this->load->language('extension/module/cubit');

        $this->document->setTitle($this->language->get('heading_success'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
		);

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_membership'),
			'href' => $this->url->link('extension/module/cubit/membership')
		);

        $data['heading_title'] = $this->language->get('heading_success');

        $data['success_message'] = sprintf($this->language->get('text_membership_success'), $this->url->link('extension/module/cubit/account', '', $this->config->get('config_ssl')));

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/module/cubit_success', $data));
    }

    public function refuse() {
        if (!$this->config->get('module_cubit_status')) {
            $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        $this->load->language('extension/module/cubit');

        $this->session->data['error'] = $this->language->get('error_membership_payment');

        $this->response->redirect($this->url->link('extension/module/cubit/salepoint', '', $this->config->get('config_secure')));
    }

    public function salepoint() {
        if (!$this->config->get('module_cubit_status')) {
            return $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        $this->load->language('extension/module/cubit');

        $this->document->setTitle($this->language->get('heading_salepoint'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home'),
		);

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_membership'),
			'href' => $this->url->link('extension/module/cubit/membership'),
		);

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        }

        $data['heading_title'] = $this->language->get('heading_salepoint');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/module/cubit_salepoint', $data));
    }

    public function account() {
        if (!$this->config->get('module_cubit_status')) {
            $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure'));

            $this->response->redirect($this->url->link('account/login', '', $this->config->get('config_secure')));
        }

        $this->load->language('extension/module/cubit');

        $this->document->setTitle($this->language->get('heading_membership'));

        $data['heading_title'] = $this->language->get('heading_membership');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

        $data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];

            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        $membership = $this->cubit->getMembership();

        if ($membership) {
            $this->load->model('extension/module/cubit');

            $subscription = $this->model_extension_module_cubit->getPaypalSubscription($membership['paypal_subscription_id']);

            if(!$subscription) {
                $subscription_info = null;
            } else {
                $update_time = new DateTime($subscription['update_time'], new DateTimeZone('UTC'));
                $update_time->setTimezone(new DateTimeZone(date_default_timezone_get()));
            
                $subscription_info = array(
                    'reference' => $subscription['id'],
                    'update_time' => $update_time->format('Y-m-d H:i:s'),
                    'cancel' => $this->url->link('extension/module/cubit/cancel', '', $this->config->get('config_secure')),
                    'status' => $subscription['status']
                );
            }

            $date_added = new DateTime($membership['date_added'], new DateTimeZone('UTC'));
            $date_added->setTimezone(new DateTimeZone(date_default_timezone_get()));

            $membership_info = array(
				'offer' => $membership['offer'],
				'billing_plan' => sprintf($this->language->get('text_billing_plan'), $this->currency->format($membership['amount'], $membership['currency'], 1, true), $membership['frequency_days']),
                'date_added' => $date_added->format('Y-m-d'),
                'subscription' => $subscription_info,
				'status' => $membership['status']
			);

            if ($membership['date_ends'] != '0000-00-00 00:00:00') {
                $date_ends = new DateTime($membership['date_ends'], new DateTimeZone('UTC'));
                $date_ends->setTimezone(new DateTimeZone(date_default_timezone_get()));

                $membership_info['date_ends'] = $date_ends->format('Y-m-d H:i');
            } else {
                $membership_info['date_ends'] = '-';
            }

            $data['membership'] = $membership_info;
        } else {
            $data['membership'] = null;
        }

        $data['text_no_membership'] = sprintf($this->language->get('text_no_membership'),   $this->url->link('extension/module/cubit/salepoint', '', $this->config->get('config_secure')));

        $data['continue'] = $this->url->link('extension/module/account');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/module/cubit_account', $data));
    }

    public function cancel() {
        if (!$this->config->get('module_cubit_status')) {
            $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure'));
            $this->response->redirect($this->url->link('account/login', '', $this->config->get('config_secure')));
        }

        $this->load->language('extension/module/cubit');
        $this->load->model('extension/module/cubit');

        $membership = $this->cubit->getMembership();

        if ($membership) {
            $subscription = $this->model_extension_module_cubit->getPaypalSubscription($membership['paypal_subscription_id']);

            if (!$subscription) {
                $this->session->data['error'] = $this->language->get('error_membership');

                $this->request->redirect($this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure')));
            } else {
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
    
                    $subscription_cancel_payload = array(
                        'reason' => $this->language->get('text_reason_user_cancel')
                    );
    
                    $subscription_cancel_request = $this->cubit->sendPaypalRequest('POST', $paypal_subscription_cancel_url, $access_token, array(), $subscription_cancel_payload);
    
                    if ($subscription_cancel_request['status'] == 204) {
                        $subscription['update_time'] = gmdate('Y-m-d H:i:s');
                        $subscription['status'] = PAYPAL_SUBSCRIPTION_STATUS_CANCELLED;
    
                        $this->model_extension_module_cubit->editPaypalSubscription($subscription['id'], $subscription);
                    } else {
                        $this->session->data['error'] = $this->language->get('error_cancel');
    
                        $this->response->redirect($this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure')));
                    }
                } catch (Exception $e) {
                    if ($this->config->get('config_error_display')) {
                        die('CUBIT ERROR: ' . $e->getMessage());
                    } else {
                        die('CUBIT ERROR');
                    }
    
                    if ($this->config->get('config_error_log')) {
                        $this->log->write('CUBIT ERROR: ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
                    }
                }
            }
        } else {
            $this->session->data['error'] = $this->language->get('error_membership');

            $this->request->redirect($this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure')));
        }

        $this->session->data['success'] = $this->language->get('text_success_cancel');

        $this->response->redirect($this->url->link('extension/module/cubit/account', '', $this->config->get('config_secure')));
    }

    public function cron() {
        $this->load->language('extension/module/cubit');

        $this->load->model('extension/module/cubit');
        $this->load->model('account/customer');

        $this->load->model('setting/setting');

        $expired_memberhsips = $this->model_extension_module_cubit->getExpiredMemberships();

        foreach ($expired_memberhsips as $membership) {
            $this->model_extension_module_cubit->editCustomerGroupId($membership['customer_id'],  $membership['expire_customer_group_id']);

            $membership['status'] = 0;

            $this->model_extension_module_cubit->editMembership($membership['cubit_membership_id'], $membership);

            if ($this->config->get('module_cubit_notify_expire') ) {
                $customer = $this->model_account_customer->getCustomer($membership['customer_id']);

                if (!$customer) {
                    continue;
                }

                if ($membership['store_id']) {
                    $this->load->model('setting/store');

                    $store_info = $this->model_setting_store->getStore($membership['store_id']);

                    if (!$store_info) {
                        $store_name = $this->config->get('config_name');

                        $store_url = $this->config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER;
                    } else {
                        $store_name = $store_info['name'];

                        if ($this->model_setting_setting->getSettingValue('config_secure', $membership['store_id'])) {
                            $store_url = $store_info['ssl'] ? $store_info['ssl'] : $store_info['url'];
                        } else {
                            $store_url = $store_info['url'];
                        }
                    }
                } else {
                    $store_name = $this->config->get('config_name');
                    $store_url = $this->config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER;
                }

                $store_owner_email = $this->model_setting_setting->getSettingValue('config_email', $membership['store_id']);

                $mail = new Mail($this->config->get('config_mail_engine'));

                $mail->adaptor->smtp_hostname =  $this->config->get('config_mail_smtp_hostname');
                $mail->adaptor->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->adaptor->smtp_password = $this->config->get('config_mail_smtp_password');
                $mail->adaptor->smtp_port = $this->config->get('config_mail_smtp_port');

                $mail->parameter = $this->config->get('config_mail_smtp_parameter');

                $mail->setTo($customer['email']);
                $mail->setFrom($store_owner_email);
                $mail->setSender($store_name);
                $mail->setReplyTo($store_owner_email);
                $mail->setSubject($this->language->get('text_membership_expired'));

                $mail->setText($this->language->get('text_membership_expired_body') . $this->config->get('config_name') . "\n\n" . $this->config->get('config_url') );

                $payload = array(
                    'title' => $this->language->get('text_membership_expired'),
                    'message' => $this->language->get('text_membership_expired_body'),
                    'store_name' => $store_name,
                    'store_url' => $store_url
                );

                $mail->setHtml($this->load->view('extension/module/cubit_email', $payload));

                $mail->send();
            }
        }

        $renewed_memberhsips = $this->model_extension_module_cubit->getRenewedMemberships();

        foreach ($renewed_memberhsips as $membership) {
            $this->model_extension_module_cubit->editCustomerGroupId($membership['customer_id'],  $membership['active_customer_group_id']);

            $membership_copy = $membership;

            $membership_copy['status'] = 1;
            $membership_copy['dare_renewed'] = gmdate('Y-m-d H:i:s');

            $this->model_extension_module_cubit->editMembership($membership['cubit_membership_id'], $membership_copy);

            if ($membership['store_id']) {
                $this->load->model('setting/store');
                $this->load->model('setting/setting');

                $store_info = $this->model_setting_store->getStore($membership['store_id']);

                if (!$store_info) {
                    $store_name = $this->config->get('config_name');
                    $store_url = $this->config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER;
                } else {
                    $store_name = $store_info['name'];

                    if ($this->model_setting_setting->getSettingValue('config_secure', $membership['store_id'])) {
                        $store_url = $store_info['ssl'] ? $store_info['ssl'] : $store_info['url'];
                    } else {
                        $store_url = $store_info['url'];
                    }
                }
            } else {
                $store_name = $this->config->get('config_name');
                $store_url = $this->config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER;
            }

            $store_owner_email = $this->model_setting_setting->getSettingValue('config_email', $membership['store_id']);

            $customer = $this->model_account_customer->getCustomer($membership['customer_id']);

            $mail = new Mail($this->config->get('config_mail_engine'));

            $mail->adaptor->smtp_hostname =  $this->config->get('config_mail_smtp_hostname');
            $mail->adaptor->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->adaptor->smtp_password = $this->config->get('config_mail_smtp_password');
            $mail->adaptor->smtp_port = $this->config->get('config_mail_smtp_port');

            $mail->parameter = $this->config->get('config_mail_smtp_parameter');

            $mail->setTo($customer['email']);
            $mail->setFrom($store_owner_email );
            $mail->setSender($store_name);
            $mail->setReplyTo($store_owner_email );
            $mail->setSubject($this->language->get('text_membership_renewed'));

            $date_ends = new DateTime($membership['date_ends'], new DateTimeZone('UTC'));
            $date_ends->setTimezone(new DateTimeZone(date_default_timezone_get()));

            $mail_subject_template = $membership['date_renewed'] == '0000-00-00 00:00:00' ? $this->language->get('text_membership_active') : $this->language->get('text_membership_renewed');
            $mail_body_template = $membership['date_renewed'] == '0000-00-00 00:00:00' ? $this->language->get('text_membership_active_body') : $this->language->get('text_membership_renewed_body');

            $mail->setText(sprintf(
                $mail_body_template,
                htmlspecialchars($membership['offer']),
                $date_ends->format('Y-m-d')
                ) . "\n\n" . $store_name . "\n\n" . $store_url );

            $payload = array(
                'title' => $mail_subject_template,
                'message' => sprintf($mail_body_template, htmlspecialchars($membership['offer']), $date_ends->format('Y-m-d H:i')) ,
                'store_name' => $store_name,
                'store_url' => $store_url
            );

            $mail->setHtml($this->load->view('extension/module/cubit_email', $payload));

            $mail->send();
        }

        die('OK');
    }

    public function webhook() {
        if (!$this->config->get('module_cubit_status')) {
            $this->response->redirect($this->url->link('error/not_found', '', $this->config->get('config_secure')));
        }

        if (!isset($this->request->server['HTTP_USER_AGENT']) || strpos($this->request->server['HTTP_USER_AGENT'], 'PayPal') !== 0) {
            webhookRespond(401, "Unauthorized");
        }

        $body = file_get_contents('php://input');

        if ($body) {
            $data = json_decode($body, true);

            $paypal_client_id = $this->config->get('module_cubit_paypal_client_id');
            $paypal_secret = $this->config->get('module_cubit_paypal_secret');

            try {
                $access_token = $this->cubit->getPaypalAccessToken($paypal_client_id, $paypal_secret, $this->config->get('module_cubit_paypal_sandbox'));

                $cert509_request = $this->cubit->sendRequest('GET', $this->request->server['HTTP_PAYPAL_CERT_URL'], array('Accept' => 'application/x-pem-file'));

                if ($cert509_request['status'] == 200) {
                    $cert509 = openssl_pkey_get_public($cert509_request['body']);

                    $webhook_id = '';

                    if ($this->config->get('module_cubit_paypal_sandbox')) {
                        $webhook_id = $this->config->get('module_cubit_paypal_sandbox_webhook_id');
                    } else {
                        $webhook_id = $this->config->get('module_cubit_paypal_webhook_id');
                    }

                    $signature_copy = implode('|', array($this->request->server['HTTP_PAYPAL_TRANSMISSION_ID'], $this->request->server['HTTP_PAYPAL_TRANSMISSION_TIME'], $webhook_id , crc32($body)));

                    if (openssl_verify($signature_copy, base64_decode($this->request->server['HTTP_PAYPAL_TRANSMISSION_SIG']), $cert509, OPENSSL_ALGO_SHA256)) {
                        $paypal_verify_url = '';

                        if ($this->config->get('module_cubit_paypal_sandbox')) {
                            $paypal_verify_url = 'https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature';
                        } else {
                            $paypal_verify_url ='https://api.paypal.com/v1/notifications/verify-webhook-signature';
                        }

                        $verify_payload = array(
							'auth_algo' => $this->request->server['HTTP_PAYPAL_AUTH_ALGO'],
							'cert_url' => $this->request->server['HTTP_PAYPAL_CERT_URL'],
							'transmission_id' => $this->request->server['HTTP_PAYPAL_TRANSMISSION_ID'],
							'transmission_sig' => $this->request->server['HTTP_PAYPAL_TRANSMISSION_SIG'],
							'transmission_time' => $this->request->server['HTTP_PAYPAL_TRANSMISSION_TIME'],
							'webhook_id' => $webhook_id,
							'webhook_event' => $data
						);

                        $verify_request = $this->cubit->sendPaypalRequest('POST', $paypal_verify_url, $access_token, array(), $verify_payload);

                        if ($verify_request['status'] == 200 && $verify_request['body']['verification_status'] == 'SUCCESS') {
                            $this->load->language('extension/module/cubit');

                            $this->load->model('extension/module/cubit');                
                            $this->load->model('account/customer');

                            if (in_array($data['event_type'], array('BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED'))) {
                                $subscription = $this->model_extension_module_cubit->getPaypalSubscription($data['resource']['id']);

                                if (!$subscription) {
                                    webhookRespond(410, 'Gone');
                                }

                                if ($data['event_type'] == 'BILLING.SUBSCRIPTION.ACTIVATED' && !in_array($subscription['status'], array(PAYPAL_SUBSCRIPTION_STATUS_APPROVAL_PENDING, PAYPAL_SUBSCRIPTION_STATUS_SUSPENDED))) {
                                    webhookRespond(409, 'Conflict');
                                }

                                if ($data['event_type'] == 'BILLING.SUBSCRIPTION.SUSPENDED' && $subscription['status'] != PAYPAL_SUBSCRIPTION_STATUS_ACTIVE) {
                                    webhookRespond(409, 'Conflict');
                                }
                                
                                if ($data['event_type'] == 'BILLING.SUBSCRIPTION.CANCELLED' && !in_array($subscription['status'], array(PAYPAL_SUBSCRIPTION_STATUS_ACTIVE, PAYPAL_SUBSCRIPTION_STATUS_SUSPENDED))) {
                                    webhookRespond(409, 'Conflict');
                                }

                                $subscription_update_time = new DateTime($subscription['update_time'], new DateTimeZone('UTC'));

                                if ($subscription_update_time->getTimestamp() < strtotime($data['resource']['update_time'])) {
                                    $subscription['update_time'] = gmdate('Y-m-d H:i:s', strtotime($data['resource']['update_time']));
                                    $subscription['status'] = $data['resource']['status'];
    
                                    $this->model_extension_module_cubit->editPaypalSubscription($subscription['id'], $subscription);

                                    webhookRespond(200, 'OK');
                                } else {
                                    webhookRespond(409, 'Conflict');
                                }
                            } else if ($data['event_type'] == 'PAYMENT.SALE.COMPLETED') {
                                $transactions = $this->model_extension_module_cubit->getPaypalTransactions(array(
                                    'id' => $data['resource']['id'],
                                    'state' => 'completed',
                                    'resource_type' => 'sale'
                                ));

                                if ($transactions) {
                                    webhookRespond(200, 'OK');
                                }

                                $membership = $this->model_extension_module_cubit->getMembershipByPaypalSubscriptionId($data['resource']['billing_agreement_id']);

                                if (!$membership) {
                                    webhookRespond(410, 'Gone');
                                }

                                $this->model_extension_module_cubit->addPaypalTransaction(array(
                                    'id' => $data['resource']['id'],
                                    'subscription' => $data['resource']['billing_agreement_id'],
                                    'amount' => $data['resource']['amount']['total'],
                                    'currency' => $data['resource']['amount']['currency'],
                                    'create_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['create_time'])),
                                    'update_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['update_time'])),
                                    'resource_type' => 'sale',
                                    'state' => $data['resource']['state']
                                ));

                                $days_forward = round($data['resource']['amount']['total'] /  $membership['amount'] * $membership['frequency_days'] );
                                
                                if ($days_forward) {
                                    if ($membership['date_ends'] == '0000-00-00 00:00:00') {
                                        $date_ends = new DateTime('now', new DateTimeZone('UTC'));

                                        //Not yet renewed.
                                        $membership['date_renewed'] = '0000-00-00 00:00:00';
                                    } else {
                                        $date_ends = new DateTime($membership['date_ends'], new DateTimeZone('UTC'));

                                        if ($date_ends->getTimestamp() < time()) {
                                            $date_ends->setTimestamp(time());
                                        }

                                        $membership['date_renewed'] = gmdate('Y-m-d H:i:s');
                                    }

                                    $date_ends->add(new DateInterval('P' . $days_forward . 'D'));
    
                                    $membership['date_ends'] = $date_ends->format('Y-m-d H:i:s');
    
                                    $this->model_extension_module_cubit->editMembership($membership['cubit_membership_id'], $membership);
                                }

                                webhookRespond(200, 'OK');
                            } else if (in_array($data['event_type'], array('PAYMENT.SALE.PENDING', 'PAYMENT.SALE.DENIED'))) {
                                if (!isset($data['resource']['billing_agreement_id'])) {
                                    webhookRespond(422, 'Unprocessable Entity');
                                }

                                $subscription = $this->model_extension_module_cubit->getPaypalSubscription($data['resource']['billing_agreement_id']);

                                if (!$subscription) {
                                    webhookRespond(410, 'Gone');
                                }

                                $this->model_extension_module_cubit->addPaypalTransaction(array(
                                    'id' => $data['resource']['id'],
                                    'subscription' => $data['resource']['billing_agreement_id'],
                                    'amount' => $data['resource']['amount']['total'],
                                    'currency' => $data['resource']['amount']['currency'],
                                    'update_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['update_time'])),
                                    'create_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['create_time'])),
                                    'resource_type' => 'sale',
                                    'state' => $data['resource']['state']
                                ));

                                webhookRespond(200, 'OK');
                            } else if (in_array($data['event_type'], array('PAYMENT.SALE.REFUNDED', 'PAYMENT.SALE.REVERSED'))) {
                                $refunds = $this->model_extension_module_cubit->getPaypalTransactions(array(
                                    'id' => $data['resource']['id'],
                                    'state' => $data['resource']['state'],
                                    'resource_type' => 'refund'
                                ));

                                if ($refunds) {
                                    webhookRespond(200, 'OK');
                                }

                                $transactions = $this->model_extension_module_cubit->getPaypalTransactions(array(
                                    'id' => $data['resource']['sale_id'],
                                    'resource_type' => 'sale'
                                ));

                                if (!$transactions) {
                                    webhookRespond(410, 'Gone');
                                }

                                $this->model_extension_module_cubit->addPaypalTransaction(array(
                                    'id' => $data['resource']['id'],
                                    'subscription' => $transactions[0]['subscription'],
                                    'amount' => $data['resource']['amount']['total'],
                                    'currency' => $data['resource']['amount']['currency'],
                                    'create_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['create_time'])),
                                    'update_time' => gmdate('Y-m-d H:i:s', strtotime($data['resource']['update_time'])),
                                    'resource_type' => 'refund',
                                    'state' => $data['resource']['state']
                                ));

                                webhookRespond(200, 'OK');
                            } else {
                                webhookResponcd(404, 'Not Found');
                            }
                        } else {
                            webhookRespond(401, 'Unauthorized (1)');
                        }
                    } else {
                        webhookRespond(401, 'Unauthorized (2)');
                    }
                } else {
                    webhookRespond(401, 'Unauthorized (3)');
                }
            } catch (Exception $e) {
                if ($this->config->get('config_error_log')) {
                    $this->log->write('CUBIT ERROR: ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
                }

                webhookRespond(500, 'Internal Server Error');
            }
        }

        webhookRespond(404, 'Not Found');
    }
}