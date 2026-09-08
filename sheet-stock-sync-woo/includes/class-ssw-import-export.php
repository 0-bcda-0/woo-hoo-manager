<?php
/**
 * Bulk stock update via CSV/XLSX upload, and a CSV export of current stock.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * No external sheet, no schedule — this is a one-shot "upload a file, we
 * update whatever SKUs are in it" tool. Perfect for "new stock arrived for
 * these 12 SKUs" style updates: export once to get the right file shape,
 * edit just the rows that changed, re-upload. Rows/SKUs not present in the
 * uploaded file are left completely untouched.
 */
class SSW_Import_Export {
	public function __construct() { add_action( 'admin_post_ssw_export_csv', array( $this, 'handle_export' ) ); }
	public function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_export_csv' );
		if ( ! SSW_License::is_active() ) { wp_die( esc_html__( 'Stock Manager is locked — enter a licence key first.', 'sheet-stock-sync-woo' ) ); }
		$settings = SSW_Settings::get_all(); $builtin = new SSW_Builtin(); $rows = $builtin->get_rows();
		nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="stock-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" ); fputcsv( $out, array( $settings['sku_column'], $settings['quantity_column'] ) );
		foreach ( $rows as $row ) { if ( '' === $row['sku'] ) { continue; } fputcsv( $out, array( $row['sku'], $row['qty'] ) ); }
		fclose( $out ); exit;
	}
	public function import( array $file ) {
		$report = array( 'success'=>false,'message'=>'','updated'=>0,'skipped'=>0,'rows'=>0,'errors'=>array(),'time'=>current_time('mysql') );
		$filename = isset($file['name']) ? $file['name'] : ''; $tmp_path = isset($file['tmp_name']) ? $file['tmp_name'] : ''; $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if ( 'csv' === $ext ) { $rows = $this->parse_csv_file($tmp_path); } elseif ( in_array($ext,array('xlsx','xlsm'),true) ) { $rows = $this->parse_xlsx_file($tmp_path); } else { $report['message']=__('Unsupported file type. Upload a .csv or .xlsx file.','sheet-stock-sync-woo'); return $report; }
		if ( is_wp_error($rows) ) { $report['message']=$rows->get_error_message(); return $report; }
		if ( empty($rows) ) { $report['message']=__('The file was read but no rows were found.','sheet-stock-sync-woo'); return $report; }
		$settings=SSW_Settings::get_all(); $header=array_map(array($this,'normalize_header'),array_shift($rows)); $sku_key=$this->normalize_header($settings['sku_column']); $qty_key=$this->normalize_header($settings['quantity_column']); $sku_index=array_search($sku_key,$header,true); $qty_index=array_search($qty_key,$header,true);
		if ( false===$sku_index || false===$qty_index ) { $report['message']=sprintf(__('Could not find columns "%1$s" and "%2$s" in the file\'s header row. Check Settings, or export a fresh template to see the expected headers.','sheet-stock-sync-woo'),$settings['sku_column'],$settings['quantity_column']); return $report; }
		$builtin=new SSW_Builtin(); $max_errors=25;
		foreach($rows as $row){ $report['rows']++; $sku=isset($row[$sku_index])?trim((string)$row[$sku_index]):''; $qty_raw=isset($row[$qty_index])?trim((string)$row[$qty_index]):'';
			if(''===$sku){$report['skipped']++;continue;} if(''===$qty_raw||!is_numeric($qty_raw)){$report['skipped']++;if(count($report['errors'])<$max_errors){$report['errors'][]=sprintf(__('SKU "%s": quantity is missing or not a number, skipped.','sheet-stock-sync-woo'),$sku);}continue;}
			$product_id=wc_get_product_id_by_sku($sku); if(!$product_id){$report['skipped']++;if(count($report['errors'])<$max_errors){$report['errors'][]=sprintf(__('SKU "%s": no matching WooCommerce product found, skipped.','sheet-stock-sync-woo'),$sku);}continue;}
			$result=$builtin->update_quantity($product_id,$qty_raw); if(is_wp_error($result)){$report['skipped']++;if(count($report['errors'])<$max_errors){$report['errors'][]=sprintf(__('SKU "%1$s": %2$s','sheet-stock-sync-woo'),$sku,$result->get_error_message());}continue;} $report['updated']++; }
		$report['success']=true; $report['message']=sprintf(__('Import complete: %1$d product(s) updated, %2$d row(s) skipped.','sheet-stock-sync-woo'),$report['updated'],$report['skipped']); return $report;
	}
	private function parse_csv_file($path){ $handle=@fopen($path,'r'); if(!$handle){return new WP_Error('ssw_cant_read_file',__('Could not read the uploaded file.','sheet-stock-sync-woo'));} $bom=fread($handle,3); if("\xEF\xBB\xBF"!==$bom){rewind($handle);} $rows=array(); while(false!==($line=fgetcsv($handle))){if(1===count($line)&&null===$line[0]){continue;}$rows[]=$line;} fclose($handle); return $rows; }
	private function parse_xlsx_file($path){ if(!class_exists('ZipArchive')){return new WP_Error('ssw_no_zip',__('This server cannot read .xlsx files (the PHP zip extension is missing). Save the file as .csv and upload that instead.','sheet-stock-sync-woo'));} $zip=new ZipArchive(); if(true!==$zip->open($path)){return new WP_Error('ssw_bad_xlsx',__('This file could not be opened as an .xlsx workbook.','sheet-stock-sync-woo'));} $shared_strings=$this->read_xlsx_shared_strings($zip); $sheet_path=$this->find_first_xlsx_sheet_path($zip); if(is_wp_error($sheet_path)){$zip->close();return $sheet_path;} $sheet_xml=$zip->getFromName($sheet_path); $zip->close(); if(false===$sheet_xml){return new WP_Error('ssw_bad_xlsx',__('Could not read the first sheet inside the .xlsx file.','sheet-stock-sync-woo'));} return $this->parse_xlsx_sheet_xml($sheet_xml,$shared_strings); }
	private function read_xlsx_shared_strings(ZipArchive $zip){$xml=$zip->getFromName('xl/sharedStrings.xml');if(false===$xml){return array();}$prev=libxml_use_internal_errors(true);$sxml=simplexml_load_string($xml);libxml_use_internal_errors($prev);if(false===$sxml){return array();}$strings=array();foreach($sxml->si as $si){if(isset($si->t)){$strings[]=(string)$si->t;}elseif(isset($si->r)){$text='';foreach($si->r as $run){$text.=isset($run->t)?(string)$run->t:'';}$strings[]=$text;}else{$strings[]='';}}return $strings;}
	private function find_first_xlsx_sheet_path(ZipArchive $zip){$workbook_xml=$zip->getFromName('xl/workbook.xml');$rels_xml=$zip->getFromName('xl/_rels/workbook.xml.rels');if(false===$workbook_xml||false===$rels_xml){return $zip->locateName('xl/worksheets/sheet1.xml',ZipArchive::FL_NOCASE)!==false?'xl/worksheets/sheet1.xml':new WP_Error('ssw_bad_xlsx',__('This does not look like a valid .xlsx file.','sheet-stock-sync-woo'));}$prev=libxml_use_internal_errors(true);$wb=simplexml_load_string($workbook_xml);$rels=simplexml_load_string($rels_xml);libxml_use_internal_errors($prev);if(false===$wb||false===$rels||!isset($wb->sheets->sheet[0])){return new WP_Error('ssw_bad_xlsx',__('This does not look like a valid .xlsx file.','sheet-stock-sync-woo'));}$r_ns=$wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$rel_id=(string)$r_ns['id'];$target='';foreach($rels->Relationship as $rel){if((string)$rel['Id']===$rel_id){$target=(string)$rel['Target'];break;}}if(''===$target){return new WP_Error('ssw_bad_xlsx',__('Could not locate the first sheet inside the .xlsx file.','sheet-stock-sync-woo'));}if('/'===substr($target,0,1)){return ltrim($target,'/');}return 'xl/'.$target;}
	private function parse_xlsx_sheet_xml($xml,array $shared_strings){$prev=libxml_use_internal_errors(true);$sxml=simplexml_load_string($xml);libxml_use_internal_errors($prev);if(false===$sxml||!isset($sxml->sheetData->row)){return array();}$rows=array();foreach($sxml->sheetData->row as $row_xml){$row_cells=array();foreach($row_xml->c as $cell){$ref=(string)$cell['r'];$col=$this->xlsx_column_letters_to_index(preg_replace('/[0-9]+/','',$ref));$type=isset($cell['t'])?(string)$cell['t']:'';if('s'===$type){$idx=isset($cell->v)?(int)$cell->v:-1;$value=isset($shared_strings[$idx])?$shared_strings[$idx]:'';}elseif('inlineStr'===$type){$value=isset($cell->is->t)?(string)$cell->is->t:'';}else{$value=isset($cell->v)?(string)$cell->v:'';}$row_cells[$col]=$value;}if(empty($row_cells)){continue;}$max_col=max(array_keys($row_cells));$ordered=array();for($i=0;$i<=$max_col;$i++){$ordered[]=isset($row_cells[$i])?$row_cells[$i]:'';}$rows[]=$ordered;}return $rows;}
	private function xlsx_column_letters_to_index($letters){$letters=strtoupper($letters);$index=0;for($i=0;$i<strlen($letters);$i++){$index=$index*26+(ord($letters[$i])-64);}return $index-1;}
	private function normalize_header($value){$value=(string)$value;$value=trim($value);$value=preg_replace('/\s+/',' ',$value);return strtolower($value);}
}
