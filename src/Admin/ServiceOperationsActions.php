<?php
declare(strict_types=1);
namespace CB\Work\Admin;
use CB\Work\Capabilities;
use CB\Work\PublicApi\ServiceBillingActions;
use CB\Work\PublicApi\ServiceOperationsActions as WorkActions;
use CB\Work\Repository\WorkSqlTransaction;
use CB\Work\ServiceOperations\Engagements;
use CB\Work\ServiceOperations\ServiceBilling;
use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Governance\Events;
defined('ABSPATH') || exit;

final class ServiceOperationsActions {
    public static function init():void {
        add_action('admin_post_cb_work_service_create',[self::class,'create']);
        add_action('admin_post_cb_work_service_bind',[self::class,'bind']);
        add_action('admin_post_cb_work_service_invoice_save',[self::class,'invoice_post']);
        add_action('wp_ajax_cb_work_service_invoice_ajax',[self::class,'invoice_ajax']);
    }
    public static function create():never {
        self::guard('cb_work_service_create');
        $input = [
            'project_id'=>absint($_POST['project_id'] ?? 0),
            'service_id'=>absint($_POST['service_id'] ?? 0),
            'starts_on'=>sanitize_text_field((string)wp_unslash($_POST['starts_on'] ?? '')),
            'label'=>sanitize_text_field((string)wp_unslash($_POST['label'] ?? '')),
        ];
        $frequency=sanitize_key((string)wp_unslash($_POST['frequency'] ?? ''));
        $timing=sanitize_key((string)wp_unslash($_POST['timing'] ?? ''));
        $rule_id=absint($_POST['rule_id'] ?? 0);
        $result=WorkSqlTransaction::run(static function()use($input,$frequency,$timing,$rule_id):int|\WP_Error {
            $id=Engagements::create($input,get_current_user_id());
            if(is_wp_error($id)){return $id;}
            $terms=ServiceBilling::create_terms($id,$frequency,$input['starts_on'],$timing,get_current_user_id());
            if(is_wp_error($terms)){return $terms;}
            if($rule_id>0){
                $linked=Engagements::bind_rule($id,$rule_id,$input['starts_on'],get_current_user_id());
                if(is_wp_error($linked)){return $linked;}
            }
            return $id;
        },new \WP_Error('work_service_create_failed'));
        if(!is_wp_error($result)){
            Audit::record(Events::SERVICE_ENGAGEMENT_CREATED,'notice',[
                'engagement_id'=>$result,'actor_user_id'=>get_current_user_id()]);
            if($rule_id>0){Audit::record(Events::SERVICE_RULE_BOUND,'notice',[
                'engagement_id'=>$result,'rule_id'=>$rule_id,'actor_user_id'=>get_current_user_id()]);}
        }
        self::redirect(is_wp_error($result)?'error':'created');
    }
    public static function bind():never {
        $id=absint($_POST['engagement_id'] ?? 0);
        self::guard('cb_work_service_bind_'.$id);
        $result=WorkActions::bind_rule($id,absint($_POST['rule_id'] ?? 0),
            sanitize_text_field((string)wp_unslash($_POST['effective_from'] ?? '')));
        self::redirect(is_wp_error($result)?'error':'bound');
    }
    public static function invoice_post():never {
        $id=absint($_POST['engagement_id'] ?? 0);
        self::guard('cb_work_service_invoice_'.$id);
        $result=self::invoice_save($id);
        self::redirect(is_wp_error($result)?'error':'saved');
    }
    public static function invoice_ajax():void {
        $id=absint($_POST['engagement_id'] ?? 0);
        if(!current_user_can(Capabilities::MANAGE) || !check_ajax_referer('cb_work_service_invoice_'.$id,'_wpnonce',false)){
            wp_send_json_error(['message'=>__('The change could not be saved.','core-blueprint-work')],403);
        }
        $result=self::invoice_save($id);
        if(is_wp_error($result)){wp_send_json_error(['message'=>__('The change could not be saved.','core-blueprint-work'),'code'=>$result->get_error_code()],409);}
        wp_send_json_success($result);
    }
    private static function invoice_save(int $id):array|\WP_Error {
        return ServiceBillingActions::record($id,
            sanitize_text_field((string)wp_unslash($_POST['month'] ?? '')),
            sanitize_key((string)wp_unslash($_POST['state'] ?? '')),
            sanitize_text_field((string)wp_unslash($_POST['invoiced_on'] ?? '')),
            sanitize_text_field((string)wp_unslash($_POST['reference'] ?? '')),
            absint($_POST['revision'] ?? 0));
    }
    private static function guard(string $nonce):void {
        if(!current_user_can(Capabilities::MANAGE)){wp_die(esc_html__('You do not have permission to manage Work.','core-blueprint-work'));}
        check_admin_referer($nonce);
    }
    private static function redirect(string $notice):never {
        wp_safe_redirect(add_query_arg(['page'=>Menu::SERVICE_OPERATIONS_SLUG,'cb_notice'=>$notice],admin_url('admin.php')));
        exit;
    }
}
