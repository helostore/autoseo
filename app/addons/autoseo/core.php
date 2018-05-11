<?php
use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/*
    entry type:
    p - product
    c - category


    entry status:
    a - active
    d - disabled
    
    update action:
    update -
    disable -
    
*/

class AutoSEO
{
    public static function delete($object_id, $object_type)
    {
        $entry_ids = db_get_fields('SELECT entry_id FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
        db_query('UPDATE ?:autoseo_entries SET ?u WHERE object_id = ?i AND object_type = ?s', array('status' => 'd'), $object_id, $object_type);
        //db_query('DELETE FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
        //if (!empty($entry_ids)) db_query('DELETE FROM ?:autoseo_links WHERE entry_id IN (?a) OR parent_id IN (?a)', $entry_ids, $entry_ids);
        /*
        $entry_ids = db_get_fields('SELECT entry_id FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
        db_query('DELETE FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
        if (!empty($entry_ids))
            db_query('DELETE FROM ?:autoseo_links WHERE entry_id IN (?a) OR parent_id IN (?a)', $entry_ids, $entry_ids);
        */
    }

    public static function update($action, $object_id, $object_type, $lang_code = '', $company_id = '', $value = '')
    {

        if (empty($lang_code)) $lang_code = CART_LANGUAGE;
        if (empty($company_id)) $company_id = Registry::get('runtime.company_id');
        if ($action == 'disable') {
            $entry_ids = db_get_fields('SELECT entry_id FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
            db_query('UPDATE ?:autoseo_entries SET ?u WHERE object_id = ?i AND object_type = ?s', array('status' => 'd'), $object_id, $object_type);
            if ($object_type == 'c') {
                $id_path = db_get_field("SELECT id_path FROM ?:categories WHERE category_id = ?i", $object_id);
                $category_ids = db_get_fields("SELECT category_id FROM ?:categories WHERE category_id = ?i OR id_path LIKE ?l ORDER BY id_path ASC", $object_id, "$id_path/%");
                $root_id = reset($category_ids);
                $parent_id = db_get_field("SELECT parent_id FROM ?:categories WHERE category_id = ?i", $root_id);
                if (!empty($category_ids))
                    db_query('UPDATE ?:autoseo_entries SET ?u WHERE object_id IN (?a) AND object_type = ?s',
                        array('parent_object_id' => $parent_id, 'parent_object_type' => 'c', 'status' => 'd'),
                        $category_ids, $object_type);
            }
            if ($object_type == 'p') {
                $category_ids = db_get_fields("SELECT category_id FROM ?:products_categories WHERE product_id = ?i ORDER BY link_type DESC, category_id ASC", $object_id);
                $parent_id = reset($category_ids);
                if (!empty($parent_id)) {
                    db_query('UPDATE ?:autoseo_entries SET ?u WHERE object_id = ?i AND object_type = ?s',
                        array('parent_object_id' => $parent_id, 'parent_object_type' => 'c', 'status' => 'd'),
                        $object_id, $object_type);
                } else die('NIY.9239');
            }
        }

        if ($action == 'update') {
            $entry = db_get_row('SELECT * FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s AND value = ?s AND company_id = ?i AND lang_code = ?s', $object_id, $object_type, $value, $company_id, $lang_code);
            $old_value = db_get_field('SELECT name FROM ?:seo_names WHERE object_id = ?i AND type = ?s AND lang_code = ?s', $object_id, $object_type, $lang_code);

            $tree = self::getTree($object_id, $object_type, $lang_code, $company_id);
            if (empty($entry)) {
                $entry = array();
                $entry['object_id'] = $object_id;
                $entry['object_type'] = $object_type;
                $entry['lang_code'] = $lang_code;
                $entry['company_id'] = $company_id;
                $entry['value'] = $value;
                $entry['parent'] = $old_value;
                $entry['status'] = 'a';
                $entry = $tree->addEntry($entry); // important cut point: inserts node into the fucking tree
                $entry_id = db_query('INSERT INTO ?:autoseo_entries ?e', $entry);
                $entry['entry_id'] = $entry_id;
                db_query('INSERT INTO ?:autoseo_links ?e', $entry);
            } else {
                if ($old_value != $value) {
                    //$entry['parent'] = $entry['value'];
                    //$entry['value'] = $old_value;
                    $entry['parent'] = $old_value;
                    $entry['x'] = 1;

                    $entry = $tree->addEntry($entry);
                    db_query('INSERT INTO ?:autoseo_links ?e', $entry);
                } else {
                    //die('Use case 3');
                }
            }
            $entry_ids = db_get_fields('SELECT entry_id FROM ?:autoseo_entries WHERE object_id = ?i AND object_type = ?s', $object_id, $object_type);
            db_query('UPDATE ?:autoseo_entries SET ?u WHERE object_id = ?i AND object_type = ?s', array('status' => 'a'), $object_id, $object_type);
        }
    }

    public static function getTree($object_id, $object_type, $lang_code, $company_id)
    {
        $tree = new ASTree($object_id, $object_type, $lang_code, $company_id);

        return $tree;
    }

    public static function getUrl($object_id, $object_type)
    {
        $url = '/';

        if (!empty($object_id)) {
            if ($object_type == 'p') {
                $url = 'products.view?product_id=' . $object_id;
            } elseif ($object_type == 'c') {
                $url = 'categories.view?category_id=' . $object_id;
            } elseif ($object_type == 'a') {
                $url = 'pages.view?page_id=' . $object_id;
            }
        }
        return $url;
    }

    public static function getDestination($entry, $fragment)
    {
        $destination = '';

        if ($entry['status'] == 'a')
            $destination = self::getUrl($entry['object_id'], $entry['object_type']);
        if ($entry['status'] == 'd')
            $destination = self::getUrl($entry['parent_object_id'], $entry['parent_object_type']);


        // TODO: redirects to same page, prevent redirect loop. Redirect somewhere else maybe..
        // for products this could mean the product was disabled
//        if ($entry['value'] === $fragment && strstr($destination, $source) !== false) {
//            return null;
//        }

        // last resort, set destination to index
//        if (empty($destination)) {
//            $destination = fn_url('');
//        }

        return $destination;
    }

    public static function resolve($uri)
    {
        $destination = AutoSEO::resolveUri($uri);

        if (!empty($destination)) {
            AutoSEO::redirect($uri, $destination);
        }
    }
    public static function resolveUri($uri)
    {
        $fragments = explode('/', $uri);
        foreach ($fragments as $k => $v) {
            if (empty($v)) {
                unset($fragments[$k]);
            }
        }
        $fragments = array_reverse($fragments);

        $destination = null;
        $langCode = null;
        foreach ($fragments as $fragment) {
            list($destination, $langCode) = AutoSEO::resolveUriFragment($fragment, $uri);
            if (!empty($destination)) {
                break;
            }
        }
        // last resort, set destination to index
        if (empty($destination)) {
            $on404GoHome = Registry::get('addons.autoseo.on_404_redirect_home');
            if ($on404GoHome === 'Y') {
                $destination = '';
            } else {
                return false;
            }
        }
        if (empty($langCode)) {
            $langCode = CART_LANGUAGE;
        }

        $destination = fn_url($destination, 'C', 'current', $langCode);

        return $destination;
    }

    public static function redirect($source, $destination)
    {
        if (!empty($destination)) {
            $developer = function_exists('is_developer') && is_developer();
            if ($developer) {
                fn_set_notification('N', "Automatic SEO Redirects", 'Resource changed location from <pre>' . $source . '</pre> to <pre>' . $destination . '</pre>', 'K');
            }
            header("HTTP/1.0 301 Moved Permanently");
            header("Location: " . $destination);
            exit;
        }
    }
    public static function resolveUriFragment($fragment, $sourceUri = null)
    {
        if (substr($fragment, -1 * strlen(SEO_FILENAME_EXTENSION)) == SEO_FILENAME_EXTENSION) {
            $fragment = substr($fragment, 0, strlen($fragment) - strlen(SEO_FILENAME_EXTENSION));
        }

        $company_id = Registry::get('runtime.company_id');
        if (!empty($company_id)) {
            $company_id = fn_get_default_company_id();
        }
        $entry = db_get_row('SELECT * FROM ?:autoseo_entries WHERE value = ?s AND lang_code = ?s AND company_id = ?i LIMIT 0,1', $fragment, CART_LANGUAGE, $company_id);
        $destination = null;
        $langCode = null;
        if (!empty($entry)) {
            $destination = AutoSEO::getDestination($entry, $fragment);
        }

        // fallback to SEO names, maybe there's a hope
        if (empty($destination)) {
            $seo = db_get_row('SELECT * FROM ?:seo_names WHERE name = ?s', $fragment);

            if (!empty($seo)) {
                $langCode = $seo['lang_code'];
                if ($seo['type'] == 'c') {
                    $destination = 'categories.view?category_id=' . $seo['object_id'];
                }
                if ($seo['type'] == 'p') {
                    $destination = 'products.view?product_id=' . $seo['object_id'];
                }
                if ($seo['type'] == 'a') {
                    $destination = 'pages.view?page_id=' . $seo['object_id'];
                }
            }
        }

        // TODO: redirects to same page, prevent redirect loop. Redirect somewhere else, maybe to index
        // for products this could mean the product was disabled
        if (!empty($entry) && $entry['value'] === $fragment && strstr($destination, $sourceUri) !== false) {
            return array('', '');
        }

        return array($destination, $langCode);
    }
}

class ASTree
{
    var $object_id, $object_type, $lang_code, $company_id;
    var $root = null;

    public function __construct($object_id, $object_type, $lang_code, $company_id)
    {
        $this->object_id = $object_id;
        $this->object_type = $object_type;
        $this->lang_code = $lang_code;
        $this->company_id = $company_id;

        $entries = db_get_hash_array('SELECT * FROM ?:autoseo_entries WHERE object_id = ?i AND company_id = ?i AND object_type = ?s AND lang_code = ?s', 'entry_id', $object_id, $company_id, $object_type, $lang_code);
        $entry_ids = array_keys($entries);
        if (!empty($entry_ids)) {
            $links = db_get_array('SELECT entry_id, parent_id FROM ?:autoseo_links WHERE (entry_id IN (?a) OR parent_id IN (?a)) AND company_id = ?i AND lang_code = ?s', $entry_ids, $entry_ids, $company_id, $lang_code);
            // todo: note that cycles (loops) will contain copies of nodes, and not instances as we would like; for now.
            foreach ($links as $link) {
                $e = array_merge($link, $entries[$link['entry_id']]);
                $this->addEntry($e);
            }
        }
    }

    public function addEntry(&$entry)
    {
        $new = new ASNode($entry);
        if (empty($this->root)) {
            $new->setParentId(0);
            $this->root = $new;
        } else {
            if (!empty($entry['parent']))
                $parentNode = $this->getNodeByValue($entry['parent']);
            else if (!empty($entry['parent_id']))
                $parentNode = $this->getNodeById($entry['parent_id']);

            if (empty($parentNode) && !empty($this->root))
                $parentNode = $this->root;
            if (!empty($parentNode)) {
                $new->setParentId($parentNode->getId());
                $parentNode->addChild($new);
            } else {
                $new->setParentId(0);
                $this->root = $new;
            }
        }
        return $new->getEntry();
    }

    public function findNodeByProp($prop, $value, $node)
    {
        if (empty($node)) return false;

        if ($node instanceof ASNode) {
            if ($node->getProp($prop) == $value) return $node;
            return $this->findNodeByProp($prop, $value, $node->children);
        } elseif (is_array($node)) {
            foreach ($node as $child) {
                $result = $this->findNodeByProp($prop, $value, $child);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        return false;
    }

    public function getNodeById($value)
    {
        return $this->findNodeByProp('entry_id', $value, $this->root);
    }

    public function getNodeByValue($value)
    {
        return $this->findNodeByProp('value', $value, $this->root);
    }
}

class ASNode
{
    var $entry;
    var $children;

    public function __construct($entry)
    {
        $this->entry = $entry;
        $this->children = array();
    }

    public function setParentId($parent_id)
    {
        $this->entry['parent_id'] = $parent_id;
    }

    public function getId()
    {
        return $this->entry['entry_id'];
    }

    public function getValue()
    {
        return $this->entry['value'];
    }

    public function getProp($prop)
    {
        return $this->entry[$prop];
    }

    public function getEntry()
    {
        return $this->entry;
    }

    public function addChild($node)
    {
        $this->children[] = $node;
    }
}