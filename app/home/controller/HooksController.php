<?php

namespace app\home\controller;

/**
 * @title 钩子文档
 * @description 接口说明：这这里编写添加的钩子文档(hook 名和hook中参数)
 */
class HooksController
{
    public function ticket_add_note()
    {
    }
    public function ticket_admin_reply()
    {
    }
    public function ticket_close()
    {
    }
    public function ticket_delete()
    {
    }
    public function ticket_delete_reply()
    {
    }
    public function ticket_department_change()
    {
    }
    public function ticket_open()
    {
    }
    public function ticket_open_admin()
    {
    }
    public function ticket_status_change()
    {
    }
    public function ticket_title_change()
    {
    }
    public function ticket_user_reply()
    {
    }
    public function after_cron()
    {
    }
    public function before_cron()
    {
    }
    public function after_five_minute_cron()
    {
    }
    public function after_daily_cron()
    {
    }
    public function before_daily_cron()
    {
    }
    public function cron_config_save()
    {
    }
    public function after_module_change_package()
    {
    }
    public function after_module_change_package_failed()
    {
    }
    public function after_module_crack_password()
    {
    }
    public function after_module_crack_password_failed()
    {
    }
    public function after_module_create()
    {
    }
    public function after_module_create_failed()
    {
    }
    public function after_module_suspend()
    {
    }
    public function after_module_suspend_failed()
    {
    }
    public function after_module_terminate()
    {
    }
    public function after_module_terminate_failed()
    {
    }
    public function after_module_unsuspend()
    {
    }
    public function after_module_unsuspend_failed()
    {
    }
    public function after_module_on()
    {
    }
    public function after_module_on_failed()
    {
    }
    public function after_module_off()
    {
    }
    public function after_module_off_failed()
    {
    }
    public function after_module_reboot()
    {
    }
    public function after_module_reboot_failed()
    {
    }
    public function after_module_hard_off()
    {
    }
    public function after_module_hard_off_failed()
    {
    }
    public function after_module_hard_reboot()
    {
    }
    public function after_module_hard_reboot_failed()
    {
    }
    public function after_module_reinstall()
    {
    }
    public function after_module_reinstall_failed()
    {
    }
    public function after_module_rescue_system()
    {
    }
    public function after_module_rescue_system_failed()
    {
    }
    public function after_module_sync()
    {
    }
    public function after_module_sync_failed()
    {
    }
    public function before_module_change_package()
    {
    }
    public function before_module_crack_password()
    {
    }
    public function before_module_create()
    {
    }
    public function before_module_renew()
    {
    }
    public function before_module_suspend()
    {
    }
    public function before_module_terminate()
    {
    }
    public function before_module_unsuspend()
    {
    }
    public function before_module_on()
    {
    }
    public function before_module_off()
    {
    }
    public function before_module_reboot()
    {
    }
    public function before_module_hard_off()
    {
    }
    public function before_module_hard_reboot()
    {
    }
    public function before_module_reinstall()
    {
    }
    public function before_module_rescue_system()
    {
    }
    public function before_module_sync()
    {
    }
    public function after_admin_add_account()
    {
    }
    public function after_admin_edit_account()
    {
    }
    public function after_admin_delete_account()
    {
    }
    public function admin_logout()
    {
    }
    public function admin_login()
    {
    }
    public function auth_admin_login()
    {
    }
    public function add_admin()
    {
    }
    public function edit_admin()
    {
    }
    public function delete_admin()
    {
    }
    public function after_admin_edit_service()
    {
    }
    public function transfer_service()
    {
    }
    public function service_delete()
    {
    }
    public function product_delete()
    {
    }
    public function product_create()
    {
    }
    public function product_edit()
    {
    }
    public function cancellation_request()
    {
    }
    public function after_product_upgrade()
    {
    }
    public function invoice_paid_before_email()
    {
    }
    public function invoice_paid()
    {
    }
    public function invoice_mark_unpaid()
    {
    }
    public function invoice_mark_cancelled()
    {
    }
    public function invoice_delete()
    {
    }
    public function invoice_refunded()
    {
    }
    public function invoice_notes()
    {
    }
    public function renew_invoice_create()
    {
    }
    public function flow_packet_invoice_create()
    {
    }
    public function invoice_combine()
    {
    }
    public function order_pass_check()
    {
    }
    public function order_cancel()
    {
    }
    public function order_delete()
    {
    }
    public function client_add()
    {
    }
    public function client_edit()
    {
    }
    public function client_close()
    {
    }
    public function pre_client_delete()
    {
    }
    public function client_delete()
    {
    }
    public function client_details_validate()
    {
        return ["Error message feedback error 1", "Error message feedback error 2"];
    }
    public function client_login()
    {
    }
    public function client_api_login()
    {
    }
    public function client_reset_password()
    {
    }
    public function client_logout()
    {
    }
    public function shopping_cart_modify_num()
    {
    }
    public function shopping_cart_settle()
    {
    }
    public function shopping_cart_add_product()
    {
    }
    public function shopping_cart_remove_product()
    {
    }
    public function shopping_cart_clear()
    {
    }
    public function server_add()
    {
    }
    public function server_delete()
    {
    }
    public function server_edit()
    {
    }
    public function before_delete_log()
    {
    }
    public function log_activity()
    {
    }
    public function affiliate_activation()
    {
    }
    public function custom_field_save()
    {
    }
    public function before_email_send()
    {
    }
    public function custom_host_create()
    {
    }
    public function after_shop_add_promo()
    {
    }
    public function check_divert_invoice()
    {
    }
    public function product_divert_upgrade()
    {
    }
    public function product_divert_delete()
    {
    }
    public function after_sign_contract()
    {
    }
    public function client_area_head_output()
    {
    }
    public function client_area_footer_output()
    {
    }
}

?>