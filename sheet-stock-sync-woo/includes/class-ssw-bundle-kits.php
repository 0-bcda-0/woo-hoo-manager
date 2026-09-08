<?php
/** Approved #21 Bundles / Kits engine. */
defined( 'ABSPATH' ) || exit;
final class SSW_Bundle_Kits {
	private static $syncing = false;
	public static function normalize_components( $rows ) {
		$out = array(); $seen = array();
		foreach ( (array) $rows as $row ) {
			$id = absint( isset( $row['product_id'] ) ? $row['product_id'] : 0 );
			$q = isset( $row['quantity'] ) ? (float) str_replace( ',', '.', (string) $row['quantity'] ) : 0;
			if ( ! $id || $q <= 0 || isset( $seen[$id] ) ) { continue; }
			$seen[$id] = true; $out[] = array( 'product_id' => $id, 'quantity' => $q );
		}
		return $out;
	}
	public static function available_units( $components, $stock_by_product ) {
		$available = null;
		foreach ( (array) $components as $c ) {
			$need = (float) $c['quantity']; if ( $need <= 0 ) { continue; }
			$stock = isset( $stock_by_product[$c['product_id']] ) ? max(0,(float)$stock_by_product[$c['product_id']]) : 0;
			$units = (int) floor( $stock / $need ); $available = null === $available ? $units : min( $available, $units );
		}
		return null === $available ? 0 : $available;
	}
	public static function component_deltas( $components, $bundle_quantity ) {
		$out = array();
		foreach ( (array) $components as $c ) { $out[(int)$c['product_id']] = -1 * (float)$bundle_quantity * (float)$c['quantity']; }
		return $out;
	}
	private static function bundles_table(){ global $wpdb; return $wpdb->prefix.'ssw_bundles'; }
	private static function components_table(){ global $wpdb; return $wpdb->prefix.'ssw_bundle_components'; }
	public static function components_for_product( $bundle_product_id ) {
		global $wpdb; $b=self::bundles_table(); $c=self::components_table();
		return $wpdb->get_results( $wpdb->prepare("SELECT c.component_product_id product_id,c.quantity FROM {$c} c INNER JOIN {$b} b ON b.id=c.bundle_id WHERE b.bundle_product_id=%d AND b.active=1 ORDER BY c.id",absint($bundle_product_id)), ARRAY_A );
	}
	public static function save_bundle( $bundle_product_id, $components ) {
		global $wpdb; $bundle_product_id=absint($bundle_product_id); $components=self::normalize_components($components);
		if(!$bundle_product_id || count($components)<1 || !wc_get_product($bundle_product_id)){ return new WP_Error('ssw_bundle_invalid',__('Valid bundle product and components are required.','sheet-stock-sync-woo')); }
		$b=self::bundles_table(); $c=self::components_table(); $now=current_time('mysql');
		$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$b} WHERE bundle_product_id=%d",$bundle_product_id));
		if($id){ $wpdb->update($b,array('active'=>1,'updated_at'=>$now),array('id'=>$id)); }
		else { $wpdb->insert($b,array('bundle_product_id'=>$bundle_product_id,'active'=>1,'created_at'=>$now,'updated_at'=>$now)); $id=(int)$wpdb->insert_id; }
		$wpdb->delete($c,array('bundle_id'=>$id));
		foreach($components as $row){ $wpdb->insert($c,array('bundle_id'=>$id,'component_product_id'=>$row['product_id'],'quantity'=>$row['quantity'])); }
		self::sync_bundle_stock($bundle_product_id); return $id;
	}
	public static function sync_bundle_stock( $bundle_product_id ) {
		if(self::$syncing){return 0;} self::$syncing=true;
		$components=self::components_for_product($bundle_product_id); $stocks=array();
		foreach($components as $c){ $p=wc_get_product((int)$c['product_id']); $stocks[(int)$c['product_id']]=$p?(float)$p->get_stock_quantity():0; }
		$qty=self::available_units($components,$stocks); $bundle=wc_get_product(absint($bundle_product_id));
		if($bundle){$bundle->set_manage_stock(true);$bundle->set_stock_quantity($qty);$bundle->save();}
		self::$syncing=false; return $qty;
	}
	public static function apply_order( $order_id ) {
		$order=wc_get_order(absint($order_id)); if(!$order || $order->get_meta('_ssw_bundle_components_applied')){return;}
		$main=SSW_Locations::ensure_main_warehouse(); if(!$main){return;}
		foreach($order->get_items('line_item') as $item){ $pid=$item->get_variation_id()?:$item->get_product_id(); $components=self::components_for_product($pid); if(!$components){continue;}
			foreach(self::component_deltas($components,(float)$item->get_quantity()) as $component_id=>$delta){ SSW_Locations::seed_product_to_main($component_id); SSW_Stock_Ledger::adjust_and_sync(array('product_id'=>$component_id,'location_id'=>$main,'delta'=>$delta,'type'=>'bundle_sale','source'=>'woocommerce_order','source_ref'=>'order-'.$order_id)); }
			self::sync_bundle_stock($pid);
		}
		$order->update_meta_data('_ssw_bundle_components_applied','yes'); $order->save();
	}
	public static function restore_order( $order_id ) {
		$order=wc_get_order(absint($order_id)); if(!$order || 'yes'!==$order->get_meta('_ssw_bundle_components_applied') || $order->get_meta('_ssw_bundle_components_restored')){return;}
		$main=SSW_Locations::ensure_main_warehouse(); if(!$main){return;}
		foreach($order->get_items('line_item') as $item){ $pid=$item->get_variation_id()?:$item->get_product_id(); $components=self::components_for_product($pid); if(!$components){continue;}
			foreach(self::component_deltas($components,-1*(float)$item->get_quantity()) as $component_id=>$delta){ SSW_Stock_Ledger::adjust_and_sync(array('product_id'=>$component_id,'location_id'=>$main,'delta'=>$delta,'type'=>'bundle_restore','source'=>'woocommerce_order','source_ref'=>'restore-order-'.$order_id)); }
			self::sync_bundle_stock($pid);
		}
		$order->update_meta_data('_ssw_bundle_components_restored','yes'); $order->save();
	}
	public static function register_hooks(){ add_action('woocommerce_order_status_processing',array(__CLASS__,'apply_order')); add_action('woocommerce_order_status_completed',array(__CLASS__,'apply_order')); add_action('woocommerce_order_status_cancelled',array(__CLASS__,'restore_order')); add_action('woocommerce_order_status_refunded',array(__CLASS__,'restore_order')); }
}
