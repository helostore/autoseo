<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
    'update_product_pre',
    'delete_product_pre',
    
    'update_category_pre',
    'delete_category_pre',
    
    // front end
    'dispatch_before_display'
);

?>