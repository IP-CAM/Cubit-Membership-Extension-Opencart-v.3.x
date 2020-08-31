<?php
//Headings
$_['heading_title'] = 'Cubit';
$_['heading_title_membership_listing'] = 'Memberships';
$_['heading_title_membership'] = 'Cubit membership #%s';

//Text
$_['text_home'] = 'Home';
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Saved';
$_['text_add'] = 'Add';
$_['text_filter'] = 'Filter';
$_['text_delete'] = 'Delete';
$_['text_confirm'] = 'Are you sure?';
$_['text_edit'] = 'Edit';
$_['text_memberships'] = 'Memberships';
$_['text_paypal_subscription_status_created'] = 'CREATED';
$_['text_paypal_subscription_status_approval_pending'] = 'APPROVAL PENDING';
$_['text_paypal_subscription_status_approved'] = 'APPROVED';
$_['text_paypal_subscription_status_active'] = 'ACTIVE';
$_['text_paypal_subscription_status_suspended'] = 'SUSPENDED';
$_['text_paypal_subscription_status_cancelled'] = 'CANCELLED';
$_['text_paypal_subscription_status_expired'] = 'EXPIRED';
$_['text_prompt_reason'] = 'Reason for changing subscription status';
$_['text_reason_cancel'] = 'Store cancelled subscription.';
$_['text_regenerate_webhook'] = 'Regenerate webhook.';
$_['text_not_set'] = 'Not set.';
$_['text_confirm'] = 'Are you sure?';
$_['text_offer'] = 'Offer';
$_['text_memberships'] = 'Memberships';
$_['text_membership_subscriptions'] = 'Membership subscriptions';
$_['text_on'] = 'On';
$_['text_off'] = 'Off';
$_['text_no_results'] = 'No results!';
$_['text_paypal_details'] = 'Paypal subfscription details';
$_['text_membership_details'] = 'Membership details';
$_['text_success_membreship_cancel'] = 'Membership cancelled.';
$_['text_missing'] = '(missing)';
$_['text_https_warning'] = 'Make sure admin/config.php HTTPS_CATALOG points to HTTPS version of your website. Paypal won\'t accept HTTP urls as webhook address.';
$_['text_billing_plan'] = '%s every %d day(s)';
$_['text_transactions'] = 'Transactions';
$_['text_sync_reload'] = 'Memberships updated.';
$_['text_membership_updated'] = 'Membership updated.';
$_['text_sale'] = 'Sale';
$_['text_refund'] = 'Refund';
$_['text_date_ends'] = 'Date ends';
$_['text_success_membership_delete'] = 'Membership deleted.';
$_['text_confirm_delete'] = 'Are you sure? All membership data will be lost.';

//Error
$_['error_required'] = 'Field is required';
$_['error_form'] = 'From has errors.';
$_['error_frequency_days'] = 'Frequency must be numeric and greater than 0.';
$_['error_delete'] = 'Faild to delete';
$_['error_payment_failure_threshold'] = 'threshold must be between 0 and 999.';
$_['error_permission'] = 'You don\'t have permission.';
$_['error_request'] = 'Request error.';
$_['error_params'] = 'Missing params.';
$_['error_paypal_credentials'] = 'Paypal client id or secret not set.';
$_['error_paypal_authentication'] = 'Paypal did not accept credentials.';
$_['error_expire_customer_group_id'] = 'Expire customer group must differ from active.';
$_['error_offer_amount'] = 'Amount is required.';
$_['error_membership'] = 'Membership does not exist.';
$_['error_subscription'] = 'Subscription does not exist.';
$_['error_persmission'] = 'You don\'t have permission to edit this page.';
$_['error_https'] = 'HTTPS_CATALOG must point to HTTPS version of your website!';
$_['error_date'] = 'Invalid date format! Use YYYY-MM-DD HH:mm:ss';

//Entries
$_['entry_paypal_secret'] = 'Paypal API secret';
$_['entry_paypal_client_id'] = 'Paypal API client';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort order';
$_['entry_sandbox'] = 'Sandbox';
$_['entry_terms'] = 'Terms';
$_['entry_name'] = 'Name';
$_['entry_frequency_days'] = 'Days';
$_['entry_payment_failure_threshold'] = 'Max payment retry count';
$_['entry_terms_option'] = 'Terms display';
$_['entry_webhook'] = 'Webhook ID';
$_['entry_offer'] = 'Offer';
$_['entry_active_customer_group_id'] = 'Membership active customer group';
$_['entry_expire_customer_group_id'] = 'Membership expire customer group';
$_['entry_amount'] = 'Amount';
$_['entry_customer'] = 'Customer';
$_['entry_notify_expire'] = 'Notify customer about expired membership';
$_['entry_cron_url'] = 'Cron URL';
$_['entry_currency_convert'] = 'Convert currencies';
$_['entry_store'] = 'Store';
$_['entry_paypal_subscription_id'] = 'Paypal subscription id';
$_['entry_paypal_subscription_status'] = 'Paypal subscription status';

//Columns
$_['column_id'] = 'ID';
$_['column_status'] = 'Status';
$_['column_create_time'] = 'Create Time';
$_['column_update_time'] = 'Update Time';
$_['column_customer'] = 'Customer';
$_['column_offer'] = 'Offer';
$_['column_amount'] = 'Amount';
$_['column_status'] = 'Status';
$_['column_date_added'] = 'Date added';
$_['column_date_renewed'] = 'Date renewed';
$_['column_date_ends'] = 'Date ends';
$_['column_paypal_subscription_id'] = 'Subscription reference';
$_['column_billing_plan'] = 'Billing plan';
$_['column_resource_type'] = 'Transaction type';
$_['column_state'] = 'State';   

//Options
$_['option_enabled'] = 'Enabled';
$_['option_disabled'] = 'Disabled';
$_['option_all'] = 'All';
$_['option_show'] = 'Show';
$_['option_require'] = 'Require';
 
//Help
$_['help_payment_failure_threshold'] = 'Number of payment failures before a membership is suspended.';
$_['help_webhook'] = 'Refresh to generate new webhook.';
$_['help_expire_customer_group_id'] = 'When membership expires customer is downgraded to expiration group.';
$_['help_cron'] = 'We recommend running running cron every 5 minutes.';
$_['help_currency_convert'] = 'Should membership plans be paid in customer currency? If yes keep setting checked.';
$_['help_date_ends_change'] = 'End date will take effect once cron recalculates membership statuses.';

//Tabs
$_['tab_settings'] = 'Settings';
$_['tab_terms'] = 'Terms';
$_['tab_offers'] = 'Offers';
$_['tab_memberships'] = 'Memberships';

//Buttons
$_['button_cancel'] = 'Cancel';
$_['button_sync'] = 'Sync';
$_['button_filter'] = 'Filter';
$_['button_return'] = 'Return';
$_['button_details'] = 'Details';
$_['button_delete_membership'] = 'Delete membership';