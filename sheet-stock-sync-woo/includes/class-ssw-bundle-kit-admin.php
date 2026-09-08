<?php
/** Approved #21 Bundles / Kits admin workflow. */
defined('ABSPATH')||exit;
final class SSW_Bundle_Kit_Admin {
	public function __construct(){ add_action('admin_menu',array($this,'menu'),25); add_action('admin_post_ssw_save_bundle_kit',array($this,'save')); }
	public function menu(){ add_submenu_page('sheet-stock-sync-woo',__('Bundles / Kits','sheet-stock-sync-woo'),__('Bundles / Kits','sheet-stock-sync-woo'),'manage_woocommerce','ssw-bundle-kits',array($this,'render')); }
	public function save(){ if(!current_user_can('manage_woocommerce')){wp_die(esc_html__('Permission denied.','sheet-stock-sync-woo'));} check_admin_referer('ssw_save_bundle_kit');
		$bundle_id=isset($_POST['bundle_product_id'])?absint($_POST['bundle_product_id']):0; $components=array();
		$ids=isset($_POST['component_product_id'])?(array)wp_unslash($_POST['component_product_id']):array(); $qtys=isset($_POST['component_quantity'])?(array)wp_unslash($_POST['component_quantity']):array();
		foreach($ids as $i=>$id){$components[]=array('product_id'=>$id,'quantity'=>isset($qtys[$i])?$qtys[$i]:1);}
		$result=SSW_Bundle_Kits::save_bundle($bundle_id,$components); wp_safe_redirect(add_query_arg(array('page'=>'ssw-bundle-kits','bundle_product_id'=>$bundle_id,'ssw_notice'=>is_wp_error($result)?'error':'saved'),admin_url('admin.php')));exit;
	}
	public function render(){ if(!current_user_can('manage_woocommerce')){wp_die(esc_html__('Permission denied.','sheet-stock-sync-woo'));}
		$id=isset($_GET['bundle_product_id'])?absint($_GET['bundle_product_id']):0; $components=$id?SSW_Bundle_Kits::components_for_product($id):array(); $available=$id?SSW_Bundle_Kits::sync_bundle_stock($id):null;
		?><div class="wrap"><h1><?php esc_html_e('Bundles / Kits','sheet-stock-sync-woo');?></h1><p><?php esc_html_e('Define component quantities. Bundle availability is calculated from the scarcest component.','sheet-stock-sync-woo');?></p>
		<?php if(isset($_GET['ssw_notice'])):?><div class="notice notice-<?php echo 'saved'===sanitize_key($_GET['ssw_notice'])?'success':'error';?> inline"><p><?php echo 'saved'===sanitize_key($_GET['ssw_notice'])?esc_html__('Bundle saved.','sheet-stock-sync-woo'):esc_html__('Bundle could not be saved.','sheet-stock-sync-woo');?></p></div><?php endif;?>
		<form method="get" action="<?php echo esc_url(admin_url('admin.php'));?>"><input type="hidden" name="page" value="ssw-bundle-kits"><label><?php esc_html_e('Bundle product / variation ID','sheet-stock-sync-woo');?> <input type="number" min="1" name="bundle_product_id" value="<?php echo esc_attr($id);?>" required></label> <button class="button"><?php esc_html_e('Open','sheet-stock-sync-woo');?></button></form>
		<?php if($id):?><p><strong><?php esc_html_e('Currently sellable bundles:','sheet-stock-sync-woo');?></strong> <?php echo esc_html($available);?></p><?php endif;?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="ssw_save_bundle_kit"><input type="hidden" name="bundle_product_id" value="<?php echo esc_attr($id);?>"><?php wp_nonce_field('ssw_save_bundle_kit');?>
		<table class="widefat striped" style="max-width:800px"><thead><tr><th><?php esc_html_e('Component product / variation ID','sheet-stock-sync-woo');?></th><th><?php esc_html_e('Qty per bundle','sheet-stock-sync-woo');?></th></tr></thead><tbody><?php for($i=0;$i<8;$i++):$row=isset($components[$i])?$components[$i]:array('product_id'=>'','quantity'=>'');?><tr><td><input type="number" min="1" name="component_product_id[]" value="<?php echo esc_attr($row['product_id']);?>"></td><td><input type="number" min="0.0001" step="0.0001" name="component_quantity[]" value="<?php echo esc_attr($row['quantity']);?>"></td></tr><?php endfor;?></tbody></table><?php submit_button(__('Save bundle','sheet-stock-sync-woo'));?></form></div><?php
	}
}
