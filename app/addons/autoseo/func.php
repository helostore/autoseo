<?php
if (!defined('BOOTSTRAP')) {
    die('Access denied');
}
use Tygh\Registry;

require_once 'core.php';


function fn_autoseo_update_product_pre($product_data, $product_id, $lang_code, $can_update = '')
{
    if ($product_data['status'] == 'D')
        AutoSEO::update('disable', $product_id, 'p');
    elseif (isset($product_data['seo_name']))
        AutoSEO::update('update', $product_id, 'p', $lang_code, $product_data['company_id'], $product_data['seo_name']);
}

function fn_autoseo_update_category_pre($category_data, $category_id, $lang_code)
{
    if ($category_data['status'] == 'D')
        AutoSEO::update('disable', $category_id, 'c');
    elseif (isset($category_data['seo_name']))
        AutoSEO::update('update', $category_id, 'c', $lang_code, $category_data['company_id'], $category_data['seo_name']);
}

function fn_autoseo_delete_product_pre($product_id, $status)
{
    AutoSEO::update('disable', $product_id, 'p');
}

function fn_autoseo_delete_category_pre($category_id, $recurse = '')
{
    AutoSEO::update('disable', $category_id, 'c');
    // Delete all subcategories
    /*
    if ($recurse == true) {
        $id_path = db_get_field("SELECT id_path FROM ?:categories WHERE category_id = ?i", $category_id);
        $category_ids	= db_get_fields("SELECT category_id FROM ?:categories WHERE category_id = ?i OR id_path LIKE ?l", $category_id, "$id_path/%");
    } else {
        $category_ids[] = $category_id;
    }
    */

}

function fn_autoseo_dispatch_before_display()
{
    // cannot work without SEO addon
    if (!defined('SEO_FILENAME_EXTENSION')) {
        return;
    }
    if (Registry::get('view')->getTemplateVars('exception_status') == CONTROLLER_STATUS_NO_PAGE) {

        AutoSEO::resolve($_SERVER['REQUEST_URI']);
    }
}
