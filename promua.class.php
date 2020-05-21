<?php
/**
 * 2016 ZSolutions
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Eugene Zubkov <magrabota@gmail.com>
 * @copyright 2016 ZSolutions
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Property of ZSolutions https://www.facebook.com/itZSsolutions/
 */

require_once(dirname(__FILE__) . '../../../config/config.inc.php');
require_once(dirname(__FILE__) . '../../../init.php');

class PromuaOrders extends PaymentModule
{
    /** @var int Current order's id */
    public $currentOrder;
    public $currencies = true;
    public $currencies_mode = 'checkbox';
    public $active = true;

    public function __construct()
    {
        $this->name = 'promua';
    }

    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown',
                                  $message = null, $extra_vars = array(), $currency_special = null, $dont_touch_amount = false,
                                  $secure_key = false, Shop $shop = null)
    {
        /*$id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown',
		$message = null, $extra_vars = array(), $currency_special = null, $dont_touch_amount = false,
		$secure_key = false, Shop $shop = null*/
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method,
            $message, $extra_vars, $currency_special, $dont_touch_amount,
            $secure_key, $shop) || die ('1');
    }
}


class Promuaclass extends ObjectModel
{
    /*Save csv from hard drive to server promua_import_products.csv*/
    public static function importCsvFile()
    {
        if (!isset($_FILES))
            echo 'Please choose file first';
        //$fileName = $_FILES['file']['name'];
        //$fileType = $_FILES['file']['type'];
        $file_error = $_FILES['file']['error'];

        if ($file_error == UPLOAD_ERR_OK) {
            //echo _PS_MODULE_DIR_.'promua/promua_import_products.csv';
            $tmp_file = Tools::file_get_contents($_FILES['file']['tmp_name']);
            file_put_contents(_PS_MODULE_DIR_ . 'promua/promua_import_products.csv', $tmp_file);
            echo 'File uploaded';
        } else {
            switch ($file_error) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = 'Error UPLOAD_ERR_INI_SIZE';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = 'Error UPLOAD_ERR_FORM_SIZE';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = 'Error UPLOAD_ERR_PARTIAL';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = 'Error UPLOAD_ERR_NO_FILE';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = 'Error UPLOAD_ERR_NO_TMP_DIR';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = 'Error UPLOAD_ERR_CANT_WRITE';
                    break;
                case  UPLOAD_ERR_EXTENSION:
                    $message = 'Error UPLOAD_ERR_EXTENSION';
                    break;
                default:
                    $message = 'Error';
                    break;
            }
            echo Tools::json_encode(array(
                'error' => true,
                'message' => $message
            ));
        }
    }

    public static function readCsvToArray()
    {
        $row = 1;
        if (($handle = fopen(_PS_MODULE_DIR_ . 'promua/promua_import_products.csv', 'r')) != false) {
            $products = array();
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                //$data = array_map("utf8_encode", $data); //encoded UTF-8
                if ($row > 1)
                    $products[] = $data;
                $row++;
            }
            fclose($handle);
        }
        //var_dump($products);
        //exit;
        return $products;
    }

    public static function importProducts()
    {
        //echo "1111111111<br>\n";
        echo "Run func: ImportProducts ...<br>\n";

        $csv = self::readCsvToArray();
        $category_import_type = self::getSettingByName('IMPORT_TARGET_TYPE');
        if ($category_import_type == 'schema')
            self::importCategories($csv);
        else
            $id_category = self::getSettingByName('IMPORT_TARGET_CATEGORY');
        $description_destination = 1;
        $default_quantity = 10;

        foreach ($csv as $c) {
            //var_dump($c);
            if ($c[21] != '') {
                if ($category_import_type == 'schema') {
                    $category_line = $c[2];
                    $id_category = self::getCategoryIdFromString($category_line, ',');

                }
                //products
                self::importProduct($c, $id_category, $description_destination, $default_quantity);
            } else {
                //combinations
                $default_quantity = 15;
            }
            //break;
        }

        echo 'products imported';
    }

    public static function importCategories($csv, $separator = ',')
    {
        $categories = array();
        foreach ($csv as $c) {
            $category_explode = explode($separator, $c[2]);
            $category_explode = array_filter(array_map('trim', $category_explode));
            $categories[] = $category_explode;
        }
        $categories = array_map('unserialize', array_unique(array_map('serialize', $categories)));

        //$categories = array_unique($categories);

        $level = 0;
        $cat0 = array();
        foreach ($categories as $cat)
            $cat0[] = array($cat[$level], 2);
        $cat0 = array_map('unserialize', array_unique(array_map('serialize', $cat0)));
        if (count($cat0) > 0) {
            foreach ($cat0 as $category)
                self::importCategory($category, $level);
            echo 'level 0 categories imported';
        }

        $level = 1;
        $cat1 = array();
        foreach ($categories as $cat) {
            $parent = $cat[0];
            $cat1[] = array($cat[1], $parent);
        }
        $cat1 = array_map('unserialize', array_unique(array_map('serialize', $cat1)));
        if (count($cat1) > 0) {
            foreach ($cat1 as $category)
                self::importCategory($category, $level);
            echo 'level 1 categories imported';
        }

        $level = 2;
        $cat2 = array();
        foreach ($categories as $cat) {
            $parent = $cat[1];
            $cat2[] = array($cat[2], $parent);
        }
        $cat2 = array_map('unserialize', array_unique(array_map('serialize', $cat2)));
        if (count($cat2) > 0) {
            foreach ($cat2 as $category)
                self::importCategory($category, $level);
            echo 'level 2 categories imported';
        }

        $level = 3;
        $cat3 = array();
        foreach ($categories as $cat) {
            $parent = $cat[2];
            $cat3[] = array($cat[3], $parent);
        }
        $cat3 = array_map('unserialize', array_unique(array_map('serialize', $cat3)));
        if (count($cat3) > 0) {
            foreach ($cat3 as $category)
                self::importCategory($category, $level);
            echo 'level 3 categories imported';
        }

        $level = 4;
        $cat4 = array();
        foreach ($categories as $cat) {
            $parent = $cat[3];
            $cat4[] = array($cat[4], $parent);
        }
        $cat4 = array_map('unserialize', array_unique(array_map('serialize', $cat4)));
        if (count($cat4) > 0) {
            foreach ($cat4 as $category)
                self::importCategory($category, $level);
            echo 'level 4 categories imported';
        }
        echo 'categories imported';
    }

    public static function importCategory($category_array, $string_level)
    {
        $level_depth = $string_level + 2;
        if (Tools::strlen($category_array[0]) < 1)
            return;
        if ($id_category = self::isCategoryExists($category_array[0], $level_depth)) {
            echo "Category {$category_array[0]} exists with id $id_category\n";
            return $id_category . "\n";
        }
        $name = self::createMultiLangField(Tools::substr($category_array[0], 0, 127));
        $link_rewrite = self::createMultiLangField(self::formatUri(Tools::substr($category_array[0], 0, 25)));
        if ($category_array[1] == '2')
            $id_parent = 2;
        else
            $id_parent = self::getParentCategoryId($category_array[1], $level_depth);
        $category = new Category;
        $category->name = $name;
        $category->link_rewrite = $link_rewrite;
        $category->active = 1;
        $category->level_depth = $level_depth;
        $category->id_parent = $id_parent;
        $category->add();
        echo "Category {$category_array[0]} imported with id {$category->id}\n";
        return $category->id;
    }

    public static function isCategoryExists($category_name, $level_depth)
    {
        $sql = 'SELECT cl.id_category
		FROM ' . _DB_PREFIX_ . 'category_lang cl
		INNER JOIN ' . _DB_PREFIX_ . 'category c ON c.id_category = cl.id_category AND level_depth=' . (int)$level_depth . '
		WHERE cl.name=\'' . $category_name . '\'';
        $res = Db::getInstance()->executeS($sql);
        $i = 0;
        foreach ($res as $r) {
            $id_category = $r['id_category'];
            $i++;
        }
        if ($i > 0)
            return $id_category;
        else
            return 0;
    }

    /*
	public static function isCategoryExists($category_name, $level_depth)
	{
		$sql = 'SELECT id_category
		FROM '._DB_PREFIX_.'category_lang
		WHERE name=\''.$category_name.'\'';
		$res = Db::getInstance()->executeS($sql);
		$i = 0;
		foreach ($res as $r)
		{
			$id_category = $r['id_category'];
			$i++;
		}
		if ($i > 0)
			return $id_category;
		else
			return 0;
	}
*/
    public static function getCategoryIdFromString($line, $separator = ',')
    {
        echo "$line\n";
        $category_array = explode($separator, $line);
        $category_array = array_filter(array_map('trim', $category_array));
        $cnt = count($category_array);
        $level = $cnt - 1;
        $level_depth = $level + 2;
        $name = $category_array[$level];
        if ($level == 0)
            return 2;

        $parent_name = $category_array[$level - 1];
        $id_parent = self::getParentCategoryId($parent_name, $level_depth);
        $id_lang = Context::getContext()->language->id;
        //$id_shop = Context::getContext()->shop->id;
        $sql = 'SELECT c.id_category 
				FROM ' . _DB_PREFIX_ . 'category c
				INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl ON cl.id_category = c.id_category AND cl.name = \'' . pSQL($name) . '\' AND cl.id_lang = ' . (int)$id_lang . '
				WHERE c.level_depth = ' . (int)$level_depth . ' AND id_parent = ' . (int)$id_parent;
        echo $sql;
        $res = Db::getInstance()->getRow($sql);
        $id_category = $res['id_category'];
        echo "getCategoryIdFromString category id - $id_category\n";
        if ($id_category)
            return $id_category;
        else
            return 2;
    }

    public static function getParentCategoryId($name, $level_depth)
    {
        $level_depth = $level_depth - 1;
        $id_lang = Context::getContext()->language->id;
        //$id_shop = Context::getContext()->shop->id;
        $sql = 'SELECT c.id_category 
				FROM ' . _DB_PREFIX_ . 'category c
				INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl ON cl.id_category = c.id_category AND cl.name = \'' . pSQL($name) . '\' AND cl.id_lang = ' . (int)$id_lang . '
				WHERE c.level_depth = ' . (int)$level_depth . ';';
        echo $sql;
        $res = Db::getInstance()->getRow($sql);
        $id_category = $res['id_category'];
        echo "Parent category $id_category\n";
        return $id_category;
    }

    public static function importProduct($product_array, $id_category, $desc_dest = 1, $default_quantity = 10)
    {
        echo "Run func: ImportProduct ...<br>\n";
        //exit;
        $name = self::createMultiLangField(Tools::substr($product_array[1], 0, 127));
        $link_rewrite = self::createMultiLangField(self::formatUri(Tools::substr($product_array[1], 0, 25)));
        $id_category = $id_category;
        $price = $product_array[5];
        $meta_keywords = self::createMultiLangField($product_array[2]);
        $description = self::createMultiLangField($product_array[3]);
        $reference = $product_array[0];
        $manufacturer = $product_array[24];
        if ($product_array[12] == '+')
            $quantity = $default_quantity;
        else
            $quantity = 0;
        $image_url = $product_array[11];

        $product = new Product();
        $product->name = $name;
        if ($desc_dest == 1)
            $product->description = $description;
        else
            $product->description_short = $description;
        $product->link_rewrite = $link_rewrite;
        $product->id_category = $id_category;
        $product->id_category_default = $id_category;
        $product->redirect_type = '404';

        $product->price = $price;
        $product->minimal_quantity = 0;
        $product->show_price = 1;
        $product->on_sale = 0;
        $product->online_only = 0;
        $product->meta_keywords = $meta_keywords;
        $product->is_virtual = 0;
        $product->reference = $reference;

        /*Manufacturer*/
        $product->manufacturer = $manufacturer;
        echo $product->manufacturer . "\n";

        if (isset($product->manufacturer) && is_numeric($product->manufacturer) && Manufacturer::manufacturerExists((int)$product->manufacturer)) {
            echo "\nexisting manufacturer 1\n";
            $product->id_manufacturer = (int)$product->manufacturer;
        } elseif (isset($product->manufacturer) && is_string($product->manufacturer) && !empty($product->manufacturer)) {
            echo "\nin here manufacturer as string 2\n";
            if ($manufacturer = Manufacturer::getIdByName($product->manufacturer))
                $product->id_manufacturer = (int)$manufacturer;
            else {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $product->manufacturer;
                $manufacturer->active = true;
                $validate_only = false;
                if (($field_error = $manufacturer->validateFields(false, true)) === true &&
                    ($lang_field_error = $manufacturer->validateFieldsLang(false, true)) === true &&
                    !$validate_only &&
                    $manufacturer->add()) {
                    $product->id_manufacturer = (int)$manufacturer->id;
                    echo "\nmanufacturer added\n";
                    //return;
                    $manufacturer->associateTo($product->id_shop_list);
                } else {
                    echo 'manufacturer error';
                    if (!$validate_only)
                        echo ' alert("manufacturer not validated");';
                    if ($field_error !== true || isset($lang_field_error) && $lang_field_error !== true)
                        echo ' alert("manufacturer ' . Db::getInstance()->getMsgError() . '");';
                }
            }
        }

        /*End Manufacturer*/

        /*Tags*/
        //$tags_array = array();
        /*End Tags*/

        //return;
        $product->add();
        //return;
        $product->addToCategories(array($id_category));
        //print_r($product);
        $id_product = $product->id;
        $product->quantity = $quantity;
        $shop = Context::getContext()->shop->id;
        StockAvailable::setQuantity((int)$product->id, 0, (int)$product->quantity, (int)$shop);

        /*Images add*/
        $image_url = trim($image_url);
        //$error = false;
        if (!empty($image_url)) {
            //$product_has_images = false;
            $image_url = str_replace(' ', '%20', $image_url);
            $shops = Shop::getShops(true, null, true);
            $image = new Image();
            $image->id_product = $id_product;
            $image->position = Image::getHighestPosition($id_product) + 1;
            $image->cover = true;
            $alt = $product->name;
            $image->legend = $alt;
            $field_error = $image->validateFields(false, true);
            $lang_field_error = $image->validateFieldsLang(false, true);
            if (($field_error === true) && ($lang_field_error === true) && ($image->add())) {
                $image->associateTo($shops);
                if (!self::copyImg($id_product, $image->id, $image_url, 'products', true))
                    $image->delete();
            }
        }
        /*End Images add*/

        /* 1.6
		$field_error = $image->validateFields(false, true);

		$lang_field_error = $image->validateFieldsLang(false, true);
		if (($field_error === true) &&
			($lang_field_error === true) && $image->add())
		{
			$image->associateTo($shops);
			//$import - new AdminImportControllerCore;
			//if (!$import->copyImg($id_product, $image->id, $image_url, 'products', true))
			if (!Amazonshopclass::copyImg($id_product, $image->id, $image_url, 'products', true))
			{
				$image->delete();
			}
		}
*/

        self::writeLog('Product - ' . $product_array[1] . ' -  IMPORTED WITH - SUCCESS');
        echo "imported $id_product to category $id_category\n";
        return true;
    }

    protected static function createMultiLangField($field)
    {
        $res = array();
        foreach (Language::getIDs(false) as $id_lang)
            $res[$id_lang] = $field;
        return $res;
    }

    public static function formatUri($string, $separator = '-')
    {
        $accents_regex = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
        $special_cases = array('&' => 'and', "'" => '');
        $string = mb_strtolower(trim($string), 'UTF-8');
        $string = str_replace(array_keys($special_cases), array_values($special_cases), $string);
        $string = preg_replace($accents_regex, '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
        $string = preg_replace('/[^a-z0-9]/u', "$separator", $string);
        $string = preg_replace("/[$separator]+/u", "$separator", $string);
        return $string;
    }

    /*Import images*/
    /**
     * copyImg copy an image located in $url and save it in a path
     * according to $entity->$id_entity .
     * $id_image is used if we need to add a watermark
     *
     * @param int $id_entity id of product or category (set in entity)
     * @param int $id_image (default null) id of the image if watermark enabled.
     * @param string $url path or url to use
     * @param string $entity 'products' or 'categories'
     * @param bool $regenerate
     * @return bool
     */
    public static function copyImg($id_entity, $id_image = null, $url, $entity = 'products', $regenerate = true)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();
                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_ . (int)$id_entity;
                break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_ . (int)$id_entity;
                break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_ . (int)$id_entity;
                break;
        }

        $url = urldecode(trim($url));
        $parced_url = parse_url($url);

        if (isset($parced_url['path'])) {
            $uri = ltrim($parced_url['path'], '/');
            $parts = explode('/', $uri);
            foreach ($parts as &$part)
                $part = rawurlencode($part);
            unset($part);
            $parced_url['path'] = '/' . implode('/', $parts);
        }

        if (isset($parced_url['query'])) {
            $query_parts = array();
            parse_str($parced_url['query'], $query_parts);
            $parced_url['query'] = http_build_query($query_parts);
        }

        if (!function_exists('http_build_url'))
            require_once(_PS_TOOL_DIR_ . 'http_build_url/http_build_url.php');

        $url = http_build_url('', $parced_url);

        $orig_tmpfile = $tmpfile;

        if (Tools::copy($url, $tmpfile)) {
            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                @unlink($tmpfile);
                return false;
            }

            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            ImageManager::resize($tmpfile, $path . '.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5,
                $src_width, $src_height);
            $images_types = ImageType::getImagesTypes($entity, true);

            if ($regenerate) {
                //$previous_path = null;
                $path_infos = array();
                $path_infos[] = array($tgt_width, $tgt_height, $path . '.jpg');
                foreach ($images_types as $image_type) {
                    $tmpfile = self::getBestPath($image_type['width'], $image_type['height'], $path_infos);

                    if (ImageManager::resize($tmpfile, $path . '-' . Tools::stripslashes($image_type['name']) . '.jpg', $image_type['width'],
                        $image_type['height'], 'jpg', false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height)) {
                        // the last image should not be added in the candidate list if it's bigger than the original image
                        if ($tgt_width <= $src_width && $tgt_height <= $src_height)
                            $path_infos[] = array($tgt_width, $tgt_height, $path . '-' . Tools::stripslashes($image_type['name']) . '.jpg');

                        if ($entity == 'products') {
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int)$id_entity . '.jpg'))
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int)$id_entity . '.jpg');
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int)$id_entity . '_' . (int)Context::getContext()->shop->id . '.jpg'))
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int)$id_entity . '_' . (int)Context::getContext()->shop->id . '.jpg');
                        }
                    }
                    if (in_array($image_type['id_image_type'], $watermark_types))
                        Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                }
            }
        } else {
            @unlink($orig_tmpfile);
            return false;
        }
        unlink($orig_tmpfile);
        return true;
    }

    private static function getBestPath($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height)
                return $path;
        }
        return $path;
    }
    /*End Import images*/


    /*End Import products*/

    public function __construct()
    {
        return true;
    }

    public static function moduleDir()
    {
        return _PS_MODULE_DIR_ . 'promua/';
    }

    public static function getAdminToken($id_employee)
    {
        $tab = 'AdminModules';
        return Tools::getAdminToken($tab . (int)Tab::getIdFromClassName($tab) . (int)$id_employee);
    }

    /*LOG*/
    public static function writeLog($log)
    {
        $date = date('m/d/Y h:i:s a', time());
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'promua_log
				VALUES(0, \'' . pSQL($date) . '\', \'' . pSQL($log) . '\')';
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return $res;
        else
            return false;
    }

    public static function clearLog()
    {
        $sql = 'TRUNCATE TABLE ' . _DB_PREFIX_ . 'promua_log';
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return $res;
        else
            return false;
    }

    public static function getLog()
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'promua_log';
        $res = Db::getInstance()->executeS($sql);
        if ($res) {
            $log = array();
            foreach ($res as $r)
                $log[] = $r['date'] . ' - ' . $r['log'];
            return $log;
        } else
            return false;
    }

    public static function ajaxGetLog()
    {
        $log = self::getLog();
        echo Tools::jsonEncode($log);
    }

    /*CRON*/
    public static function isRunningCron()
    {
        $last = self::getSettingByName('LAST_CRON_CHECK');
        $now = time();
        $period = $now - $last;
        if ($period < 8)
            return 1;
        else
            return 0;
    }

    public static function ajaxIsRunningCron()
    {
        echo self::isRunningCron();
    }

    /*END CRON*/

    /*SETTINGS*/
    public static function getSettings()
    {
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_settings;';
        $res = Db::getInstance()->executeS($sql);
        if ($res)
            return $res;
        else
            return false;
    }

    public static function setConfig($id = false, $value = false)
    {
        if (!$id)
            $id = Tools::getValue('id');
        if (!$value)
            $value = Tools::getValue('value');
        if ($id && isset($value)) {
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'promua_settings
					SET 
						value=\'' . pSQL($value) . '\'
					WHERE id=' . (int)$id;
            $res = Db::getInstance()->execute($sql);

            $sets = self::getSettings();
            $settings = array();
            foreach ($sets as $set)
                $settings[] = $set['value'];
            if ($res)
                return true;
            else
                return false;
        } else
            return false;
    }

    public static function getSettingByName($name)
    {
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_settings
				WHERE `option`=\'' . pSQL($name) . '\';';
        $res = Db::getInstance()->getRow($sql);
        if ($res['value'])
            return $res['value'];
        else
            return false;
    }
    /* END SETTINGS*/

    /*ORDERS*/
    public static function getOrderId($id_order_promua)
    {
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_orders
				WHERE id_order_promua=' . (int)$id_order_promua;
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_order_prestashop']))
            return $res['id_order_prestashop'];
        else
            return false;
    }

    public static function getOrderInfo($id_order_promua)
    {
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_orders
				WHERE id_order_promua=' . (int)$id_order_promua;
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_order_prestashop']))
            return $res;
        else
            return false;
    }

    public static function setOrderBridge($id_order_prestashop, $id_order_promua, $id_cart, $id_address, $state,
                                          $amount_paid, $id_currency, $id_customer, $id_carrier)
    {
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'promua_orders
				VALUES(0, ' . (int)$id_order_prestashop . ',' . (int)$id_order_promua . ', ' . (int)$id_cart . '
				, ' . (int)$id_address . ', \'' . pSQL($state) . '\', ' . (float)$amount_paid . ', ' . (int)$id_currency . '
				, ' . (int)$id_customer . ', ' . (int)$id_carrier . ')';
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return true;
        else
            return false;
    }

    public static function updateOrderBridge($boo)
    {
        //id_order_prestashop, $id_order_promua, $id_cart, $id_address, $state, $amount_paid, $id_currency, $id_customer
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'promua_orders
				SET
					id_address=' . (int)$boo['id_address'] . ',
					state=\'' . pSQL($boo['state']) . '\',
					amount_paid=' . (float)$boo['amount_paid'] . ',
					id_carrier=' . (int)$boo['id_carrier'] . '
				WHERE id_order_promua=' . (int)$boo['id_order_promua'];
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return true;
        else
            return false;
    }

    public static function createCustomer($customer_name, $customer_email)
    {
        $customer_firstname = 'Prom.ua';
        $customer = new Customer();
        $customer->firstname = $customer_firstname;
        $customer->lastname = $customer_name;
        $customer->email = $customer_email;
        $customer->passwd = Tools::passwdGen();
        $customer->add();
        if ($customer->id)
            return $customer->id;
        else
            return false;
    }

    public static function getCartQty($id_cart, $id_product)
    {
        $sql = 'SELECT quantity FROM ' . _DB_PREFIX_ . 'cart_product WHERE id_cart=' . (int)$id_cart . ' and id_product=' . (int)$id_product;
        $res = Db::getInstance()->getRow($sql);
        return $res['quantity'];
    }

    public static function updateCart($id_customer, $id_product, $quantity, $id_currency, $id_cart = false)
    {
        echo "function updateCart \n";
        if (!$id_cart) {
            echo "new Cart \n";
            // Create Cart
            echo "$id_customer, $id_product, $quantity, $id_currency, $id_cart";
            $cart = new Cart();
            $cart->id_customer = $id_customer;
            $cart->id_currency = $id_currency;
            //$cart->id_currency = 2;
            $product = new Product();
            $product->id = $id_product;
            //$product->id_lang = ;

            $cart->add();
            if ($cart->id) {
                $cart->updateQty($quantity, $id_product, null, false);
                //self::setCartProductName($cart->id, $id_product, $product->name, $product->link_rewrite);
                return $cart->id;
            } else
                return false;
        } else {
            echo "Update Cart \n";
            // Update Cart
            $cart = new Cart();
            $cart->id = $id_cart;
            $cart->id_customer = $id_customer;
            $cart->id_currency = $id_currency;
            $lang = self::getSettingByName('CATALOG_LANGUAGE');
            $id_lang = Language::getIdByIso($lang);
            $cart->id_lang = $id_lang;
            $quantity_before = self::getCartQty($id_cart, $id_product);
            $qty = $quantity - $quantity_before;
            echo "id_currency {$cart->id_currency}" . ' Q' . $quantity . ' - QB' . $quantity_before . " - $qty\n";

            $id_product_attribute = 0;
            if ($qty > 0)
                $cart->updateQty($qty, $id_product, $id_product_attribute, false, 'up');
            if ($qty < 0) {
                $qty = $qty * (-1);
                $cart->updateQty($qty, $id_product, $id_product_attribute, false, 'down');
            }
            return $cart->id;
        }
    }

    public static function getProductName($id_product, $id_lang = false)
    {
        if (!$id_lang) {
            $lang = self::getSettingByName('CATALOG_LANGUAGE');
            $id_lang = Language::getIdByIso($lang);
        }
        $sql = 'SELECT name, link_rewrite
				FROM ' . _DB_PREFIX_ . 'product_lang
				WHERE id_product=' . (int)$id_product . ' AND id_lang=' . (int)$id_lang;
        $res = Db::getInstance()->getRow($sql);
        return array($res['name'], $res['link_rewrite']);
    }

    public static function updateCartProductsNames($products)
    {
        foreach ($products as &$product) {
            $id_product = $product['id_product'];
            $product_option = self::getProductName($id_product);
            $product['name'] = $product_option[0];
            $product['link_rewrite'] = $product_option[1];
        }
        return $products;
    }

    public static function findAddress($id_customer, $address1)
    {
        $sql = 'SELECT id_address 
				FROM  ' . _DB_PREFIX_ . 'address
				WHERE id_customer=' . (int)$id_customer . ' and address1="' . pSQL($address1) . '"';
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_address']))
            return $res['id_address'];
        else
            return false;
    }

    public static function createAddress($customer_name, $address1, $delivery_type, $phone,
                                         $id_customer = null, $company = false)
    {
        $id_country = Country::getByIso('UA');
        $city = Tools::substr($address1, 0, strpos($address1, ','));
        $address1 = str_replace('(', '', $address1);
        $address1 = str_replace(')', '', $address1);
        $address = new Address();
        $address->id_customer = $id_customer;
        $address->firstname = 'Prom.ua';
        $address->lastname = $customer_name;
        $address->address1 = $address1;
        $address->address2 = 'Prom.ua ' . $delivery_type;
        $address->id_country = $id_country;
        $address->city = $city;
        $address->phone = $phone;
        $address->phone_mobile = $phone;
        if ($company)
            $address->company = $company;
        $alias = 'promua' . Tools::passwdGen();
        $address->alias = $alias;
        $address->add();
        if ($address->id)
            return $address->id;
        else
            return false;
    }

    public static function getIdOrderByTemplate($template)
    {
        $sql = 'SELECT id_order_state 
				FROM  `' . _DB_PREFIX_ . 'order_state_lang`
				WHERE template="' . pSQL($template) . '"';
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_order_state']))
            return $res['id_order_state'];
        else
            return false;
    }


    public static function getIdCurrencyByISO($iso)
    {
        $sql = 'SELECT id_currency 
				FROM  `' . _DB_PREFIX_ . 'currency`
				WHERE iso_code="' . pSQL($iso) . '"';
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_currency']))
            return $res['id_currency'];
        else
            return false;
    }

    public static function getOrderState($state)
    {
        if ((Configuration::get('PS_SHOP_DOMAIN') == 'nazaritoys.com.ua') || (Configuration::get('PS_SHOP_DOMAIN_SSL') == 'nazaritoys.com.ua'))
            return 3;

        switch ($state) {
            case 'new' :
                return self::getIdOrderByTemplate('preparation');
            case 'accepted' :
                return self::getIdOrderByTemplate('payment');
            case 'declined' :
                return self::getIdOrderByTemplate('order_canceled');
            case 'draft' :
                return self::getIdOrderByTemplate('preparation');
            case 'closed' :
                return self::getIdOrderByTemplate('shipped');
            default:
                exit;
        }
    }

    public static function updateOrderAddress($id_order, $id_address)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'orders
				SET
					id_address=' . (int)$id_address . ', 
				WHERE id_order=' . (int)$id_order;
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return true;
        else
            return false;
    }

    public static function createOrder($id_customer, $id_cart, $id_order_state, $amount_paid, $id_address, $id_currency,
                                       $payment, $date_add, $reference, $id_carrier)
    {
        //echo "id_currency$id_currency id_carrier$id_carrier";
        $id_shop = 1;
        $id_shop_group = 1;
        $order = new Order();
        $order->id_address_delivery = $id_address;
        $order->id_address_invoice = $id_address;
        $order->id_cart = $id_cart;
        $order->id_currency = $id_currency;
        $order->id_customer = $id_customer;
        $order->id_carrier = $id_carrier;
        $order->current_state = $id_order_state;
        $order->secure_key = md5(Tools::passwdGen());
        $order->payment = $payment;
        $order->id_shop = $id_shop;
        $order->id_shop_group = $id_shop_group;
        $order->module = 'promua';
        $order->total_paid = $amount_paid;
        $order->total_paid_tax_incl = $amount_paid;
        $order->total_paid_tax_excl = $amount_paid;
        $order->total_paid_real = $amount_paid;
        $order->total_products = $amount_paid;
        $order->total_products_wt = $amount_paid;
        $currency_row = Currency::getCurrency($id_currency);
        $conversion_rate = $currency_row['conversion_rate'];
        $order->conversion_rate = $conversion_rate;
        $order->date_add = $date_add;
        $order->reference = $reference;
        $order->add();
        if ($order->id) {
            $cart = new Cart();
            $cart->id = $id_cart;
            $use_taxes = 0;
            $order_invoice = 0;
            $order_detail = new OrderDetail();
            $order_detail->createList($order, $cart, $id_order_state, self::updateCartProductsNames($cart->getProducts()),
                (isset($order_invoice) ? $order_invoice->id : 0), $use_taxes);
            return $order->id;
        } else
            return false;

    }

    public static function updateCarrier($id_order, $id_carrier)
    {
        $sql = 'UPDATE  `' . _DB_PREFIX_ . 'orders`
					SET
						id_carrier=' . (int)$id_carrier . '
					WHERE id_order=' . (int)$id_order;
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return true;
        else
            return false;
    }

    public static function findCarrier($name)
    {
        $sql = 'SELECT id_carrier 
		FROM  `' . _DB_PREFIX_ . 'carrier`
		WHERE name LIKE "%' . pSQL($name) . '%"';
        $res = Db::getInstance()->getRow($sql);
        if (isset($res['id_carrier']))
            return $res['id_carrier'];
        else
            return false;
    }

    public static function createCarrier($name)
    {
        $carrier = new Carrier;
        $carrier->name = $name;
        $carrier->active = 1;
        $carrier->delay = 'Promua';
        $carrier->add();
        if ($carrier->id)
            return $carrier->id;
        else
            return false;
    }


    public static function addOrderCarrier($id_order, $id_carrier)
    {
        $carrier = new OrderCarrier();
        $carrier->id_order = $id_order;
        $carrier->id_carrier = $id_carrier;
        $carrier->add();
    }


    public static function addOrderPayment($id_order, $payment_method)
    {
        $order = new Order($id_order);
        $order_payment = new OrderPayment();
        $order_payment->order_reference = $order->reference;
        $order_payment->id_currency = $order->id_currency;
        $order_payment->amount = $order->total_paid;
        $order_payment->payment_method = $payment_method;
        $order_payment->conversion_rate = $order->conversion_rate;
        $order_payment->add();
    }

    public static function addOrderHistory($id_order, $id_order_state)
    {
        $history = new OrderHistory();
        $history->id_order = $id_order;
        $history->id_order_state = $id_order_state;
        $history->id_employee = 0;
        $history->add();
    }


    /* Orders Import */
    public static function importOrders()
    {
        echo 'importOrders()';
        echo Configuration::get('PS_SHOP_DOMAIN');
        $orders_url = self::getSettingByName('ORDERS_UPDATE_URL');
        $xml = Tools::file_get_contents($orders_url);
        $x = simplexml_load_string($xml);
        //print_r($x);
        $k = 0;
        foreach ($x->order as $order) {
            $id_order_promua = $order['id'];
            // nazari_fix

            $order_state = $order['state'];
            $state = $order_state;
            // only new
            if ($order_state != 'new')
                continue;

            /*bridge_order_object*/
            $boo = self::getOrderInfo($id_order_promua);
            if (stripos($boo['amount_paid'], '.') == false)
                $boo['amount_paid'] = $boo['amount_paid'] . '.00';
            $id_order_prestashop = $boo['id_order_prestashop'];

            /*Define Basic required variables from current order XML*/
            $customer_name = $order->name;
            $customer_email = $order->email;
            $phone = $order->phone;
            $delivery_type = $order->deliveryType[0];
            $id_carrier = self::findCarrier($delivery_type);
            //print_r($delivery_type);
            if (!$id_carrier)
                $id_carrier = self::createCarrier($delivery_type);
            //$delivery = $order->deliveryType;
            $address = $order->address;
            $address = str_replace('(', '', $address);
            $address = str_replace(')', '', $address);
            $address = Tools::substr($address, 0, 127);

            if ($id_order_prestashop) {
                $id_customer = $boo['id_customer'];
                $id_address = self::findAddress($id_customer, $address);
            }
            $currency = $order->items->item[0]->currency;
            $price = $order->{'price' . $currency};
            //$amount_paid = $order->priceUSD;
            $amount_paid = $price;
            $date_add = $order->date;
            $payment = $order->paymentType[0];
            $payment = 'PROM.UA - ' . $payment;

            $id_order_state = self::getOrderState($state);
            if ($order->company)
                $company = $order->company;
            else
                $company = false;
            /* END Define Basic required variables from current order XML*/

            // If didnt find order in order bridge then create order and cart
            echo "\nIs existing order?\n";
            if (!$id_order_prestashop) {
                echo "Start Create Order\n";

                echo "Start Create Customer\n";
                /*Customer*/
                $customer = new Customer();
                if (Validate::isEmail($customer_email))
                    $customer->getByEmail($customer_email);
                if (!$customer->id)
                    $id_customer = self::createCustomer($customer_name, $customer_email);
                else
                    $id_customer = $customer->id;
                //echo 'id_customer = '.$id_customer.'<br>';

                echo "Shipping address\n";
                /*Shipping Address*/
                $id_address = self::findAddress($id_customer, $address);
                if (!$id_address)
                    $id_address = self::createAddress($customer_name, $address, $delivery_type,
                        $phone, $id_customer, $company);
                /*End Shipping Address*/
                echo "Customer Messages\n";
                $comment = $order->payercomment[0];
                /*End Customer*/
                echo "End Create Customer\n";

                /*
				$id_currency_default = Currency::getDefaultCurrency()->id;
				$currency_row = Currency::getCurrency($id_currency);
				$conversion_rate = $currency_row['conversion_rate'];
				*/

                echo "Start Create Cart\n";
                /*Cart*/
                $id_cart = false;

                $id_currency = Currency::getIdByIsoCode($currency);
                foreach ($order->items->item as $item) {
                    print_r($item);
                    $quantity = $item->quantity;
                    $sync_type = self::getSettingByName('ORDERS_TYPE');
                    if ($sync_type == 'sku') {
                        $product_sku = $item->sku[0];
                        echo $product_sku . "\n";
                        if ((Configuration::get('PS_SHOP_DOMAIN') == 'nazaritoys.com.ua') || (Configuration::get('PS_SHOP_DOMAIN_SSL') == 'nazaritoys.com.ua'))
                            $id_product = self::getProductIdBySkuNazar($product_sku); // nazar
                        else
                            $id_product = self::getProductIdBySku($product_sku);
                        $id_cart = self::updateCart($id_customer, $id_product, $quantity, $id_currency, $id_cart);
                    } else {
                        $id_product = $item->external_id;
                        $id_cart = self::updateCart($id_customer, $id_product, $quantity, $id_currency, $id_cart);
                    }
                    //echo 'id_cart = '.$id_cart.'<br>';
                }
                /*End Cart*/

                echo "Start Order\n";

                /*Create Order*/
                $id_order = self::createOrder($id_customer, $id_cart, $id_order_state, $amount_paid, $id_address,
                    $id_currency, $payment, $date_add, $id_order_promua, $id_carrier);
                //echo 'id_order = '.$id_order.'<br>';
                if ($id_order) {
                    if (Tools::strlen($comment) > 1)
                        self::addCustomerMessage($id_customer, $comment, $id_order);
                    self::addOrderPayment($id_order, $payment);
                    self::addOrderHistory($id_order, $id_order_state);
                    self::addOrderCarrier($id_order, $id_carrier);
                    self::setOrderBridge($id_order, $id_order_promua, $id_cart, $id_address, $state, $amount_paid,
                        $id_currency, $id_customer, $id_carrier);
                }
                echo "End Create Order\n";
                /*End Create Order*/
            } elseif (($boo['state'] != $order['state']) || ($boo['id_address'] != $id_address)
                || (($boo['amount_paid'] != $amount_paid) && ($boo['amount_paid'] . '.00' != $amount_paid)) || ($boo['id_carrier'] != $id_carrier)) {
                echo "EXISTING ORDER UPDATE\n" . $boo['amount_paid'] . $amount_paid . "\n";
                if (1 == 1) {
                    echo "\nSkip order update\n";
                    continue;
                }
                /*
				address - Update Shipping Address
				paymentType -
				deliveryType -
				payercomment -
				priceUSD - Update Cart
				state - Update state
				*/

                //$p_order = new Order($boo['id_order_prestashop']);

                if ($boo['id_carrier'] != $id_carrier) {
                    //Update carrier in oredrs and bridge
                    self::updateCarrier($id_order, $id_carrier);
                    $boo['id_carrier'] = $id_carrier;
                }

                if ($boo['state'] != $order['state']) {
                    // Update State in order
                    $status = $id_order_state;
                    $history = new OrderHistory();
                    $history->id_order = (int)$boo['id_order_prestashop'];
                    $history->changeIdOrderState($status, ($boo['id_order_prestashop'])); //order status
                    // Update State in bridge
                    $boo['state'] = $order['state'];
                }

                if ($boo['id_address'] != $id_address) {
                    //Add Shipping Address if not exists
                    if (!$id_address)
                        $id_address = self::createAddress($customer_name, $address,
                            $delivery_type, $phone, $id_customer, $company);
                    // Update Shipping Address in order and bridge
                    if (self::updateOrderAddress($boo['id_order_prestashop'], $id_address))
                        $boo['id_address'] = $id_address;
                }
                //echo "updateOrderBridge START\n";

                if ($boo['amount_paid'] != $amount_paid) {
                    // Update Order
                    /* Update Cart*/
                    echo "\namount_paid not equal\n";
                    echo "\nupdateOrderBridge START$id_cart\n";

                    $id_cart = $boo['id_cart'];
                    echo "updateOrderBridge START{$boo['id_cart']}\n";
                    foreach ($order->items->item as $item) {
                        $quantity = (int)$item->quantity[0];
                        $sync_type = self::getSettingByName('ORDERS_TYPE');
                        if ($sync_type == 'sku') {
                            $product_sku = $item->sku[0];
                            if ((Configuration::get('PS_SHOP_DOMAIN') == 'nazaritoys.com.ua') || (Configuration::get('PS_SHOP_DOMAIN_SSL') == 'nazaritoys.com.ua'))
                                $id_product = self::getProductIdBySkuNazar($product_sku); // nazar
                            else
                                $id_product = self::getProductIdBySku($product_sku);
                        } else
                            $id_product = (int)$item->external_id[0];

                        //print_r($id_product);
                        echo $id_product . "\n";
                        $currency = $item->currency;
                        $id_currency = Currency::getIdByIsoCode($currency);
                        echo "Play Update Order2 - $id_currency - $id_cart\n";
                        if ($id_product > 0)
                            $id_cart = self::updateCart((int)$id_customer, $id_product, $quantity, $id_currency, (int)$id_cart);
                        else
                            exit;
                        //echo 'id_cart = '.$id_cart.'<br>';
                    }
                    /*End Cart*/

                    // Update Amount Paid in order and bridge
                }

                echo "UPDATE ORDER BRIDGE\n";
                self::updateOrderBridge($boo);
            } // End IF Order exists in Bridge
            $k++;
        } // end foreach order
        self::writeLog('Orders Script Finished');

    }

    public static function addCustomerMessage($id_customer, $message, $id_order)
    {
        echo 'addCustomerMessage';
        $customer = new Customer((int)$id_customer);
        if (!Validate::isLoadedObject($customer))
            echo 'The customer is invalid.';
        elseif (!$message)
            echo 'The message cannot be blank.';
        else {
            //check if a thread already exist
            $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $id_order);
            if (!$id_customer_thread) {
                $customer_thread = new CustomerThread();
                $customer_thread->id_contact = 0;
                $customer_thread->id_customer = (int)$id_customer;
                $customer_thread->id_shop = (int)Context::getContext()->shop->id;
                $customer_thread->id_order = (int)$id_order;
                $customer_thread->id_lang = (int)Context::getContext()->language->id;
                $customer_thread->email = $customer->email;
                $customer_thread->status = 'open';
                $customer_thread->token = Tools::passwdGen(12);
                $customer_thread->add();
            } else
                $customer_thread = new CustomerThread((int)$id_customer_thread);

            $customer_message = new CustomerMessage();
            $customer_message->id_customer_thread = $customer_thread->id;
            $customer_message->id_employee = 0;
            $customer_message->message = $message;
            $customer_message->private = 0;

            if (!$customer_message->add())
                echo 'An error occurred while saving the message.';
        }
        echo 'endAddCustomerMessage';
    }

    /*END ORDERS*/

    /*CATEGORIES*/
    public static function deleteCategoriesRelation()
    {
        $id_category = Tools::getValue('id_category');
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'promua_bridge
				WHERE id_category_prestashop=' . (int)$id_category;
        $res = Db::getInstance()->execute($sql);
        if ($res)
            return true;
    }

    /* Save category relation to bridge */
    public static function saveBridge()
    {
        $p = Tools::getValue('param');
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_bridge
				WHERE id_category_prestashop=' . (int)$p[0];
        $res = Db::getInstance()->getRow($sql);
        //file_put_contents('D:\Openserver\domains\smartmart.com\prestashop161\modules\promua\t.txt', 'ok1');
        if ($res) {
            //file_put_contents('D:\Openserver\domains\smartmart.com\prestashop161\modules\promua\t.txt', 'ok2');
            $name = iconv(mb_detect_encoding($p[2], mb_detect_order(), true), 'UTF-8', $p[2]);
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'promua_bridge
					SET promua_portal_category_id=' . (int)$p[1] . ',
					category_promua_name=\'' . pSQL($name) . '\',
					category_promua_url=\'' . pSQL($p[3]) . '\'
					WHERE id_category_prestashop=' . (int)$p[0];
            //file_put_contents(_PS_MODULE_DIR_.'promua/test.txt', $sql.PHP_EOL);
            $res = Db::getInstance()->execute($sql);
            return $res;
        } else {
            //file_put_contents('D:\Openserver\domains\smartmart.com\prestashop161\modules\promua\t.txt', 'ok3');
            $name = iconv(mb_detect_encoding($p[2], mb_detect_order(), true), 'UTF-8', $p[2]);
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'promua_bridge
					VALUES (0, ' . (int)$p[0] . ', ' . (int)$p[1] . ', \'' . pSQL($name) . '\', \'' . pSQL($p[3]) . '\');';
            //file_put_contents(_PS_MODULE_DIR_.'promua/test.txt', $sql.PHP_EOL);
            $res = Db::getInstance()->execute($sql);
            //return $res;
            return '$res';
        }
    }

    public static function ajaxLoadPortalCategoryId()
    {
        $param = Tools::getValue('param');
        $sql = 'SELECT promua_portal_category_id, category_url
				FROM ' . _DB_PREFIX_ . 'promua_portal_categories
				WHERE category_1=\'' . pSQL($param[0]) . '\' and category_2=\'' . pSQL($param[1]) . '\'
					and category_3=\'' . pSQL($param[2]) . '\' and category_4=\'' . pSQL($param[3]) . '\'';
        $res = Db::getInstance()->getRow($sql);
        echo $res['promua_portal_category_id'] . '|' . $res['category_url'];
        //return true;
    }

    public static function ajaxGetPromCategoryTree()
    {
        $promua_portal_category_id = Tools::getValue('promua_portal_category_id');
        $sql = 'SELECT *
				FROM ' . _DB_PREFIX_ . 'promua_portal_categories
				WHERE promua_portal_category_id=' . (int)$promua_portal_category_id;
        //file_put_contents('D:\Openserver\domains\smartmart.com\prestashop161\modules\promua\t.txt', $sql);
        $res = Db::getInstance()->getRow($sql);
        //echo $res['category_1'].'||'.$res['category_2'].'||'.$res['category_3'].'||'.$res['category_4'];
        $out = array($res['category_1'], $res['category_2'], $res['category_3'], $res['category_4']);
        /*$out - line of category path*/

        /*Load tree*/
        self::ajaxLoadSubCategoriesTree($out);
    }

    public static function ajaxLoadSubCategoriesTree($param)
    {
        $res = array();
        $j = 1;
        foreach ($param as $p) {
            if ($p !== '') {
                $path = array('', '', '', '');
                for ($i = 0; $i < $j; $i++)
                    $path[$i] = $param[$i];
                $res[] = self::loadSubCategories($path);
            }
            $j++;
        }
        array_unshift($res, array('param' => $param));
        echo Tools::jsonEncode($res);
        //file_put_contents('D:\Openserver\domains\smartmart.com\prestashop161\modules\promua\t.txt', $sql);
    }

    public static function loadSubCategories($param = false)
    {
        $level = 4;
        $name = $param[3];
        $parent = $param[2];

        if ($param[3] == '') {
            $level = 3;
            $name = $param[2];
            $parent = $param[1];
        }
        if ($param[2] == '') {
            $level = 2;
            $name = $param[1];
            $parent = $param[0];
        }
        if ($param[1] == '') {
            $level = 1;
            $name = $param[0];
        }
        $parent_level = $level - 1;
        //$child_level = $level + 1;
        if ($level == 1) {
            $sql = 'SELECT promua_portal_category_id, category_1 as category
					FROM ' . _DB_PREFIX_ . 'promua_portal_categories
					GROUP BY category_1
					ORDER BY category_1;';
            $res = Db::getInstance()->executeS($sql);
        } else {
            $sql = 'SELECT promua_portal_category_id, category_' . (int)$level . ' as category 
					FROM ' . _DB_PREFIX_ . 'promua_portal_categories
					WHERE category_' . (int)$parent_level . '=\'' . pSQL($parent) . '\' and category_' . (int)$level . '<>\'\'
					GROUP BY category_' . (int)$level . ';';
            //file_put_contents(_PS_MODULE_DIR_.'promua/test.txt', $sql.PHP_EOL, FILE_APPEND);
            $res = Db::getInstance()->executeS($sql);
        }
        array_unshift($res, array('name' => $name));
        array_unshift($res, array('level' => $level));
        return $res;
    }


    public static function ajaxLoadSubCategories($param = false)
    {
        if (!$param)
            $param = Tools::getValue('param');

        //file_put_contents(_PS_MODULE_DIR_.'promua/test1.txt', $param[0].$param[1].$param[2].$param[3].PHP_EOL);

        if ($param[0] !== '')
            $promua_portal_category_id = 1;
        else
            $promua_portal_category_id = 0;

        if ($promua_portal_category_id) {
            $level = 6;

            if ($param[3] == '') {
                $level = 4;
                $name = $param[2];
            }
            if ($param[2] == '') {
                $level = 3;
                $name = $param[1];
            }
            if ($param[1] == '') {
                $level = 2;
                $name = $param[0];
            }

            $parent_level = $level - 1;
            //$child_level = $level + 1;
            if ($level < 5) {
                $sql = 'SELECT promua_portal_category_id, category_' . (int)$level . ' as category 
						FROM ' . _DB_PREFIX_ . 'promua_portal_categories 
						WHERE category_' . (int)$parent_level . '=\'' . pSQL($name) . '\' and category_' . (int)$level . '<>\'\'
						GROUP BY category_' . (int)$level . ';';
                //file_put_contents(_PS_MODULE_DIR_.'promua/test.txt', $sql.PHP_EOL, FILE_APPEND);
                $res = Db::getInstance()->executeS($sql);
                if (count($res) < 1)
                    $level = 5;
            } else {
                $level = 5;
                $res = array();
            }

            array_unshift($res, array('parent' => $name));
            array_unshift($res, array('level' => $level));
            //echo Tools::jsonEncode($res);
            //return $res;
            return Tools::jsonEncode($res);
        } else {
            $sql = 'SELECT promua_portal_category_id, category_1 as category
					FROM ' . _DB_PREFIX_ . 'promua_portal_categories
					GROUP BY category_1
					ORDER BY category_1;';
            $res = Db::getInstance()->executeS($sql);
            //print_r($res);
            $name = '';
            $level = 1;
            array_unshift($res, array('parent' => $name));
            array_unshift($res, array('level' => $level));
            return Tools::jsonEncode($res);
        }
    }

    /*Generate Categories for Products Feed*/
    public static function generateXmlCatalog($id_lang = 1)
    {
        $sql = 'SELECT c.id_category, c.id_parent, cl.name, pb.*
				FROM `' . _DB_PREFIX_ . 'category` c 
				LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category and cl.id_lang=' . (int)$id_lang . '
				LEFT JOIN `' . _DB_PREFIX_ . 'promua_bridge` pb ON pb.id_category_prestashop = c.id_category 
				WHERE c.active=1';
        $res = Db::getInstance()->executeS($sql);
        $o = '';
        foreach ($res as $r) {
            if ($r['id_category'] > 2) {
                if ($r['promua_portal_category_id'] !== null)
                    $portal = ' portal_id="' . $r['promua_portal_category_id'] . '"';
                else
                    $portal = '';
                $name = str_replace('&', '&amp;', $r['name']);
                if ($r['id_category'] != 2) {
                    if ($r['id_parent'] == 2)
                        $o .= '<category id="' . $r['id_category'] . '" ' . $portal . '>' . $name . '</category>' . PHP_EOL;
                    else
                        $o .= '<category id="' . $r['id_category'] . '" parentID="' . $r['id_parent'] . '" ' . $portal . '>' . $name . '</category>' . PHP_EOL;
                }
            }
        }
        return $o;
    }


    /**
     * Get product images and legends
     *
     * @param int $id_lang Language id for multilingual legends
     * @return array Product images and legends
     */
    public static function getImages($id_proiduct, $id_lang)
    {
        return Db::getInstance()->executeS('
			SELECT image_shop.`cover`, i.`id_image`, il.`legend`, i.`position`
			FROM `' . _DB_PREFIX_ . 'image` i
			' . Shop::addSqlAssociation('image', 'i') . '
			LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ')
			WHERE i.`id_product` = ' . (int)$id_proiduct . '
			ORDER BY `position`'
        );
    }

    public static function getCombinationImages($id_product_attribute)
    {
        $query = 'SELECT id_image
				FROM ' . _DB_PREFIX_ . 'product_attribute_image
				WHERE id_product_attribute=' . (int)$id_product_attribute;
        //echo $query;
        if ($rows = Db::getInstance()->executeS($query))
            return $rows;
        else
            return false;
    }

    /*Generate YML Products Feed*/
    public static function generatePromuaYmlFeed($id_lang = 1, $id_shop = 1)
    {
        //$id_lang = 1;
        echo 'Start generating feed';
        $lang = self::getSettingByName('CATALOG_LANGUAGE');
        $id_lang = (int)Language::getIdByIso($lang);
        $id_prefix = '';

        $id_shop = (int)Context::getContext()->shop->id;

        $currency_code = self::getSettingByName('CATALOG_CURRENCY');
        $id_currency = Currency::getIdByIsoCode($currency_code);
        $currency_row = Currency::getCurrency($id_currency);
        $conversion_rate = $currency_row['conversion_rate'];
        //$currency_code_lower = Tools::strtolower($currency_code);
        $currency_rate = $conversion_rate;

        $shop_name = Configuration::get('PS_SHOP_NAME');
        $shop_url = 'http://' . Configuration::get('PS_SHOP_DOMAIN');
        $id_product_attribute = 0;
        echo _DB_PREFIX_ . "_DB_PREFIX\n";
        $sql = 'SELECT a.id_product, a.id_category_default, a.price, sa.quantity, pl.name, pl.description, 
						pl.description_short, pl.meta_keywords, pl.meta_description, pi.id_image, p.id_manufacturer, p.reference
				FROM `' . _DB_PREFIX_ . 'product_shop` a
				LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON sa.id_product=a.id_product and sa.id_product_attribute=
				' . (int)$id_product_attribute . ' and sa.id_shop=' . (int)$id_shop . '
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON pl.id_product=a.id_product and pl.id_lang=' . (int)$id_lang . '
				LEFT JOIN ' . _DB_PREFIX_ . 'image pi ON a.id_product = pi.id_product AND pi.position = 1 
				LEFT JOIN ' . _DB_PREFIX_ . 'product p ON a.id_product = p.id_product
				WHERE a.active=1;';
        $res = Db::getInstance()->executeS($sql);
        /* XML explanation - http://support.prom.ua/documents/467 */
        $o = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $o .= '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . PHP_EOL;
        $o .= '<yml_catalog date="2010-04-01 17:00">' . PHP_EOL;
        $o .= '<shop>' . PHP_EOL;
        $o .= '<name>' . $shop_name . '</name>' . PHP_EOL;
        $o .= '<company>' . $shop_name . '</company>' . PHP_EOL;
        $o .= '<url>' . $shop_url . '</url>' . PHP_EOL;
        $o .= '<currencies><currency id="' . $currency_code . '" rate="' . $currency_rate . '" plus="0"/></currencies>' . PHP_EOL;

        $o .= '<categories>' . PHP_EOL;
        // Generate catalog
        $o .= self::generateXmlCatalog($id_lang);
        $o .= '</categories>' . PHP_EOL;

        $o .= '<offers>' . PHP_EOL;
        //$o .= '<test>'.PHP_EOL;
        // each product
        foreach ($res as $r) {
            $id_product = $r['id_product'];
            echo 'Generating product ' . $id_product_attribute . PHP_EOL;
            if ($combinations = self::getCombinations($id_product))
                $group_id = '10100' . $id_product;
            else
                $group_id = false;
            $o .= '<offer available="true" id="' . $id_prefix . $r['id_product'] . '" ' . ($group_id ? 'group_id="' . $group_id . '"' : '') . '>' . PHP_EOL;
            $name = str_replace('&', '&amp;', $r['name']);
            $o .= '<name>' . $name . '</name>' . PHP_EOL;

            if (Tools::strlen($r['description_short']) > 2)
                $ds = $r['description_short'] . '. ';
            else
                $ds = '';
            $o .= '<description>' . strip_tags($ds) . strip_tags($r['description']) . '</description>' . PHP_EOL;
            $sku = $r['reference'];
            $o .= '<vendorCode>' . $sku . '</vendorCode>' . PHP_EOL;

            $id_manufacturer = $r['id_manufacturer'];
            $manufacturer = Manufacturer::getNameById($id_manufacturer);
            $o .= '<vendor>' . $manufacturer . '</vendor>' . PHP_EOL;

            /*Categories*/
            $o .= '<categoryId>' . $r['id_category_default'] . '</categoryId>' . PHP_EOL;
            /*
				$product_categories = self::getProductCategories($id_product, $r['id_category_default']);
				foreach ($product_categories as $id_product_category)
				{
					$o .= '<categoryId>'.$id_product_category.'</categoryId>'.PHP_EOL;
				}
				*/

            /*Price*/
            $price_final = Product::getPriceStatic($r['id_product']);
            $price = $price_final;
            $price = $conversion_rate * $price;
            $price = round($price, 2);

            $o .= '<currencyId>' . $currency_code . '</currencyId>' . PHP_EOL;
            $o .= '<price>' . $price_final . '</price>' . PHP_EOL;
            $o .= '<quantity_in_stock>' . $r['quantity'] . '</quantity_in_stock>' . PHP_EOL;
            if ($r['quantity'] > 0)
                $o .= '<pickup>true</pickup>' . PHP_EOL;
            else
                $o .= '<pickup>false</pickup>' . PHP_EOL;

            $o .= '<delivery>true</delivery>' . PHP_EOL;

            /*image*/
            $images = self::getImages($id_product, $id_lang);
            //print_r($images);
            $l_i = 0;
            foreach ($images as $image_obj) {
                $id_images = $image_obj['id_image'];
                $img_path = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'img/p/';
                $length = Tools::strlen($id_images);
                for ($i = 0; $i < $length; $i++)
                    $img_path .= $id_images[$i] . '/';
                $main_img_path = $img_path .= $id_images . '.jpg';
                $o .= '<picture>' . $img_path . '</picture>' . PHP_EOL;
                $l_i++;
                if ($l_i == 10)
                    break;
            }

            /*/image*/

            /*Features*/

            echo 'Have features?';
            if ($features = self::getFeatures($id_product, $id_lang))
                echo 'Start generating features';
            foreach ($features as $feature)
                $o .= '<param name="' . $feature['name'] . '">' . $feature['value'] . '</param>' . PHP_EOL;
            /*End Features*/

            /*Пытаемся получить первую комбинацию*/
            if ($combinations = self::getCombinations($id_product)) {
                $combinationsFirst = self::getCombinationsOne($id_product);
                foreach ($combinationsFirst as $combinationsFirsts)
                    $id_product_attribute = $combinationsFirsts['id_product_attribute'];
                $combination_detail = self::getCombinationDetails($id_product_attribute, $id_lang);
                foreach ($combination_detail as $cd)
                    $o .= '<param name="' . $cd['group_name'] . '">' . $cd['name'] . '</param>' . PHP_EOL;
            }
            /*Конец. Пытаемся получить первую комбинацию*/

            /*Keywords*/
            $keywords = $r['meta_keywords'];
            //echo "\nkeywords $keywords\n";
            if (Tools::strlen($keywords) > 1)
                $o .= '<keywords>' . $keywords . '</keywords>' . PHP_EOL;
            $o .= '</offer>' . PHP_EOL;
            //break;
            /*Combinations*/
            if ($combinations) {
                $unique = array();
                foreach ($combinations as $combination) {
                    $id_product_attribute = $combination['id_product_attribute'];
                    if (in_array($id_product_attribute, $unique))
                        continue;
                    else
                        $unique[] = $id_product_attribute;
                    echo 'found combination ' . $id_product_attribute;
                    $combination_detail = self::getCombinationDetails($id_product_attribute, $id_lang);
                    $id = $id_prefix . $r['id_product'] . '_' . $id_product_attribute;
                    $o .= '<offer available="true" id="' . $id . '" ' . ($group_id ? 'group_id="' . $group_id . '"' : '') . ' >' . PHP_EOL;
                    $cn = array();

                    foreach ($combination_detail as $cd)
                        $cn[] = $cd['name'];

                    $comb_name = $combination_name = implode(', ', $cn);
                    $combination_name = $name . ' ' . $combination_name;
                    $o .= '<name>' . $combination_name . '</name>' . PHP_EOL;
                    $o .= '<description/>' . PHP_EOL;
                    $sku = $combination['reference'];
                    $o .= '<vendorCode>' . $sku . '</vendorCode>' . PHP_EOL;
                    $o .= '<vendor>' . $manufacturer . '</vendor>' . PHP_EOL;

                    $o .= '<categoryId>' . $r['id_category_default'] . '</categoryId>' . PHP_EOL;
                    $price_final = Product::getPriceStatic($r['id_product'], true, $id_product_attribute);
                    $price = $price_final;
                    $price = $conversion_rate * $price;
                    $price = round($price, 2);

                    $o .= '<currencyId>' . $currency_code . '</currencyId>' . PHP_EOL;
                    $o .= '<price>' . $price . '</price>' . PHP_EOL;

                    $o .= '<quantity_in_stock>' . $combination['quantity'] . '</quantity_in_stock>' . PHP_EOL;

                    if ($combination['quantity'] > 0)
                        $o .= '<pickup>true</pickup>' . PHP_EOL;
                    else
                        $o .= '<pickup>false</pickup>' . PHP_EOL;

                    $o .= '<delivery>true</delivery>' . PHP_EOL;

                    /*image*/
                    $l_i = 0;
                    $comb_images = self::getCombinationImages($id_product_attribute);

                    foreach ($comb_images as $comb_image) {
                        echo "\nHave images\n";
                        echo $id_images;
                        $id_images = $comb_image['id_image'];
                        $img_path = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'img/p/';
                        $length = Tools::strlen($id_images);
                        for ($i = 0; $i < $length; $i++)
                            $img_path .= $id_images[$i] . '/';
                        $img_path .= $id_images . '.jpg';
                        if (isset($id_images))
                            $o .= '<picture>' . $img_path . '</picture>' . PHP_EOL;
                        else
                            $o .= '<picture>' . $main_img_path . '</picture>' . PHP_EOL;
                        $l_i++;
                        if ($l_i == 10)
                            break;
                    }

                    /*/image*/

                    /*Features*/
                    foreach ($combination_detail as $cd)
                        $o .= '<param name="' . $cd['group_name'] . '">' . $cd['name'] . '</param>' . PHP_EOL;
                    /*End Features*/

                    /*Keywords*/
                    $combination_keywords = $keywords . ', ' . $comb_name;
                    if (Tools::strlen($combination_keywords) > 1)
                        $o .= '<keywords>' . $combination_keywords . '</keywords>' . PHP_EOL;
                    $o .= '</offer>' . PHP_EOL;
                }
                echo PHP_EOL;
            }
            /*End Combinations*/
            echo PHP_EOL;
            //break;
        }

        $o .= '</offers>' . PHP_EOL;
        $o .= '</shop>' . PHP_EOL;
        $o .= '</yml_catalog>' . PHP_EOL;

        file_put_contents(_PS_MODULE_DIR_ . 'promua/ps_cml_feed.xml', $o);
        self::writeLog('Products feed generated');
    }

    public static function getProductCategories($id_product, $id_category_default)
    {
        $product_categories = Product::getProductCategories($id_product);
        $product_categories[] = $id_category_default;
        $product_categories = array_unique($product_categories);
        return $product_categories;
    }


    public static function getFeatures($id_product, $id_lang)
    {
        $query = 'SELECT fp.*, fl.name, fvl.value
				FROM ' . _DB_PREFIX_ . 'feature_product fp
				LEFT JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON fp.id_feature=fl.id_feature AND fl.id_lang=' . (int)$id_lang . '
				LEFT JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON fvl.id_feature_value=fp.id_feature_value AND fvl.id_lang=' . (int)$id_lang . '
				WHERE id_product=' . (int)$id_product;
        if ($rows = Db::getInstance()->executeS($query))
            return $rows;
        else
            return false;
    }

    public static function getCombinations($id_product)
        //* Вот тут я добавали LIMIT для вывода всех комбинаций кроме первой.
    {
        $query = 'SELECT pa.*
				FROM ' . _DB_PREFIX_ . 'product_attribute pa
				WHERE id_product=' . (int)$id_product . '
                LIMIT 1, 100';
        //echo $query;
        if ($rows = Db::getInstance()->executeS($query))
            return $rows;
        else
            return false;
    }

    /* Добавил для получения только первой комбинации. */
    public static function getCombinationsOne($id_product) //* Вот тут я добавали LIMIT для вывода всех комбинаций кроме первой.
    {
        $query = 'SELECT pa.*
				FROM ' . _DB_PREFIX_ . 'product_attribute pa
				WHERE id_product=' . (int)$id_product . '
                LIMIT 1';
        //echo $query;
        if ($rows = Db::getInstance()->executeS($query))
            return $rows;
        else
            return false;
    }

    /* Конец. Добавил для получения только первой комбинации. */

    public static function getCombinationDetails($id_product_attribute, $id_lang)
    {
        $query = 'SELECT pac.*, al.name, agl.name as group_name
				FROM ' . _DB_PREFIX_ . 'product_attribute_combination pac
				LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON a.id_attribute=pac.id_attribute
				LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON agl.id_attribute_group=a.id_attribute_group AND agl.id_lang=' . (int)$id_lang . '
				LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON al.id_attribute=pac.id_attribute AND al.id_lang=' . (int)$id_lang . '
				WHERE pac.id_product_attribute=' . (int)$id_product_attribute;
        if ($rows = Db::getInstance()->executeS($query))
            return $rows;
        else
            return false;
    }

    /*Generate Products Feed*/
    public static function generatePromuaXmlFeed($id_lang = 1, $id_shop = 1)
    {
        //$id_lang = 1;
        $lang = self::getSettingByName('CATALOG_LANGUAGE');
        $id_lang = Language::getIdByIso($lang);

        $id_shop = (int)Context::getContext()->shop->id;

        $currency_code = self::getSettingByName('CATALOG_CURRENCY');
        $id_currency = Currency::getIdByIsoCode($currency_code);
        $currency_row = Currency::getCurrency($id_currency);
        $conversion_rate = $currency_row['conversion_rate'];
        $currency_code_lower = Tools::strtolower($currency_code);
        $currency_rate = $conversion_rate;

        $shop_name = Configuration::get('PS_SHOP_NAME');
        $id_product_attribute = 0;

        $sql = 'SELECT a.id_product, a.id_category_default, a.price, sa.quantity, pl.name, pl.description, 
						pl.description_short, pl.meta_keywords, pi.id_image
				FROM `' . _DB_PREFIX_ . 'product_shop` a
				LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON sa.id_product=a.id_product and sa.id_product_attribute=
				' . (int)$id_product_attribute . ' and sa.id_shop=' . (int)$id_shop . '
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON pl.id_product=a.id_product and pl.id_lang=' . (int)$id_lang . ' and sa.id_shop=
				' . (int)$id_shop . '
				LEFT JOIN ' . _DB_PREFIX_ . 'image pi ON a.id_product = pi.id_product AND pi.position = 1 
				WHERE a.active=1;';
        $res = Db::getInstance()->executeS($sql);
        /* XML explanation - http://support.prom.ua/documents/467 */
        $o = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $o .= '<prestashop_connector_promua>' . PHP_EOL;
        $o .= '<name>' . $shop_name . '</name>' . PHP_EOL;
        $o .= '<currency code="' . $currency_code . '">' . $currency_rate . '</currency>' . PHP_EOL;
        $o .= '<catalog>' . PHP_EOL;
        // Generate catalog
        $o .= self::generateXmlCatalog($id_lang);
        $o .= '</catalog>' . PHP_EOL;

        $o .= '<items>' . PHP_EOL;
        // each product
        foreach ($res as $r) {

            $o .= '<item id="' . $r['id_product'] . '">' . PHP_EOL;
            $name = str_replace('&', '&amp;', $r['name']);
            $o .= '<name>' . $name . '</name>' . PHP_EOL;
            if (Tools::strlen($r['description_short']) > 2)
                $ds = $r['description_short'] . '. ';
            else
                $ds = '';
            $o .= '<description><![CDATA[' . $ds . $r['description'] . ']]></description>' . PHP_EOL;
            $o .= '<categoryId>' . $r['id_category_default'] . '</categoryId>' . PHP_EOL;
            $price_final = Product::getPriceStatic($r['id_product']);
            $price = $price_final;
            $price = $conversion_rate * $price;
            $price = round($price, 2);
            $o .= '<price' . $currency_code_lower . '>' . $price_final . '</price' . $currency_code_lower . '>' . PHP_EOL;

            if ($r['quantity'] > 0)
                $o .= '<available>true</available>' . PHP_EOL;
            else
                $o .= '<available>false</available>' . PHP_EOL;
            /*image*/
            $id_images = $r['id_image'];
            $img_path = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'img/p/';
            $length = Tools::strlen($id_images);
            for ($i = 0; $i < $length; $i++)
                $img_path .= $id_images[$i] . '/';
            $img_path .= $id_images . '.jpg';
            $o .= '<image>' . $img_path . '</image>' . PHP_EOL;
            /*/image*/
            /*Keywords*/
            $keywords = $r['meta_keywords'];
            if (Tools::strlen($keywords) > 1)
                $o .= '<keywords>' . $keywords . '</keywords>' . PHP_EOL;
            $o .= '</item>' . PHP_EOL;
            //break;
        }

        $o .= '</items>' . PHP_EOL;
        $o .= '</prestashop_connector_promua>' . PHP_EOL;

        file_put_contents(_PS_MODULE_DIR_ . 'promua/ps_cml_feed.xml', $o);
        self::writeLog('Products feed generated');
    }

    public static function categoriesToJson()
    {
        $categories = Category::getCategories();
        $cats = Tools::jsonEncode($categories);
        file_put_contents(_PS_MODULE_DIR_ . 'promua/views/js/ps_categories.json', $cats);
    }

    /* List of prestashop categories from block Category*/
    public static function categoryList()
    {
        $id_lang = Context::getContext()->language->id;
        $id_lang = Language::getIdByIso(self::getSettingByName('CATALOG_LANGUAGE'));
        $phpself = Context::getContext()->controller->php_self;
        $current_allowed_controllers = array('category');

        if ($phpself != null && in_array($phpself, $current_allowed_controllers) && Configuration::get('BLOCK_CATEG_ROOT_CATEGORY')
            && isset(Context::getContext()->cookie->last_visited_category) && Context::getContext()->cookie->last_visited_category) {
            $category = new Category(Context::getContext()->cookie->last_visited_category, $id_lang);
            if (Configuration::get('BLOCK_CATEG_ROOT_CATEGORY') == 2 && !$category->is_root_category && $category->id_parent)
                $category = new Category($category->id_parent, $id_lang);
            elseif (Configuration::get('BLOCK_CATEG_ROOT_CATEGORY') == 3 && !$category->is_root_category
                && !$category->getSubCategories($category->id, true))
                $category = new Category($category->id_parent, $id_lang);
        } else
            $category = new Category((int)Configuration::get('PS_HOME_CATEGORY'), $id_lang);

        //$cacheId = $this->getCacheId($category ? $category->id : null);

        if (1 == 1) {
            $range = '';
            $max_depth = Configuration::get('BLOCK_CATEG_MAX_DEPTH');
            if (Validate::isLoadedObject($category)) {
                if ($max_depth > 0)
                    $max_depth += $category->level_depth;
                $range = 'AND nleft >= ' . (int)$category->nleft . ' AND nright <= ' . (int)$category->nright;
            }

            $result_ids = array();
            $result_parents = array();
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT c.id_parent, c.id_category, cl.name, cl.description, cl.link_rewrite, level_depth, pb.promua_portal_category_id,
			pb.category_promua_name, pb.category_promua_url
			FROM `' . _DB_PREFIX_ . 'category` c
			LEFT JOIN `' . _DB_PREFIX_ . 'promua_bridge` pb ON pb.id_category_prestashop=c.id_category
			INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.`id_lang` = 
			' . (int)Context::getContext()->language->id . Shop::addSqlRestrictionOnLang('cl') . ')
			INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON (cs.`id_category` = c.`id_category` AND cs.`id_shop` = 
			' . (int)Context::getContext()->shop->id . ')
			WHERE (c.`active` = 1 OR c.`id_category` = ' . (int)Configuration::get('PS_HOME_CATEGORY') . ')
			AND c.`id_category` != ' . (int)Configuration::get('PS_ROOT_CATEGORY') . '
			' . ((int)$max_depth != 0 ? ' AND `level_depth` <= ' . (int)$max_depth : '') . '
			' . $range . '
			AND c.id_category IN (
				SELECT id_category
				FROM `' . _DB_PREFIX_ . 'category_group`
				WHERE `id_group` IN (' . pSQL(implode(', ', Customer::getGroupsStatic(1))) . ')
			)
			ORDER BY `level_depth` ASC, ' . (Configuration::get('BLOCK_CATEG_SORT') ? 'cl.`name`' : 'cs.`position`') . ' '
                . (Configuration::get('BLOCK_CATEG_SORT_WAY') ? 'DESC' : 'ASC'));
            //WHERE `id_group` IN ('.pSQL(implode(', ', Customer::getGroupsStatic((int)Context::getContext()->customer->id))).')
            foreach ($result as &$row) {
                $result_parents[$row['id_parent']][] = &$row;
                $result_ids[$row['id_category']] = &$row;
            }

            $block_categ_tree = self::getTree($result_parents, $result_ids, $max_depth, ($category ? $category->id : null));
            Context::getContext()->smarty->assign('blockCategTree', $block_categ_tree);

            if ((Tools::getValue('id_product') || Tools::getValue('id_category'))
                && isset(Context::getContext()->cookie->last_visited_category)
                && Context::getContext()->cookie->last_visited_category) {
                $category = new Category(Context::getContext()->cookie->last_visited_category, $id_lang);
                if (Validate::isLoadedObject($category))
                    Context::getContext()->smarty->assign(array('currentCategory' => $category, 'currentCategoryId' => $category->id));
            }

            Context::getContext()->smarty->assign('isDhtml', Configuration::get('BLOCK_CATEG_DHTML'));
            if (file_exists(_PS_THEME_DIR_ . 'modules/promua/listcategories.tpl'))
                Context::getContext()->smarty->assign('branche_tpl_path', _PS_THEME_DIR_ .
                    'modules/promua/views/templates/admin/category-tree-branch.tpl');
            else
                Context::getContext()->smarty->assign('branche_tpl_path', _PS_MODULE_DIR_ .
                    'promua/views/templates/admin/category-tree-branch.tpl');
        }
        //return $this->display(__FILE__, 'listcategories.tpl', $cacheId);
    }

    public static function getTree($result_parents, $result_ids, $max_depth, $id_category = null, $current_depth = 0)
    {
        if (is_null($id_category))
            $id_category = Context::getContext()->shop->getCategory();
        $children = array();
        if (isset($result_parents[$id_category]) && count($result_parents[$id_category]) && ($max_depth == 0 || $current_depth < $max_depth))
            foreach ($result_parents[$id_category] as $subcat)
                $children[] = self::getTree($result_parents, $result_ids, $max_depth, $subcat['id_category'], $current_depth + 1);
        if (isset($result_ids[$id_category])) {
            $link = Context::getContext()->link->getCategoryLink($id_category, $result_ids[$id_category]['link_rewrite']);
            $name = $result_ids[$id_category]['name'];
            $desc = $result_ids[$id_category]['description'];
            $level_depth = $result_ids[$id_category]['level_depth'];
            $cid = $result_ids[$id_category]['promua_portal_category_id'];
            $cname = $result_ids[$id_category]['category_promua_name'];
            $curl = $result_ids[$id_category]['category_promua_url'];
        } else
            $link = $name = $desc = '';

        $return = array(
            'id' => $id_category,
            'link' => $link,
            'name' => $name,
            'desc' => $desc,
            'level_depth' => $level_depth,
            'children' => $children,
            'cid' => $cid,
            'cname' => $cname,
            'curl' => $curl
        );
        return $return;
    }

    /* End list of prestashop categories from block Category*/

    public static function getProductIdBySkuNazar($product_sku)
    {
        if (preg_match('/000([1-9][0-9]*)/is', $product_sku, $match))
            $product_sku = $match[1];
        //$id_product = $product_sku;

        echo $product_sku . "\n";
        //return $id_product;

        $query = 'SELECT fp.id_product, fvl.id_feature_value
				FROM ' . _DB_PREFIX_ . 'feature_value_lang fvl 
				LEFT JOIN ' . _DB_PREFIX_ . 'feature_product fp ON fvl.id_feature_value=fp.id_feature_value AND id_feature=42
				WHERE value=\'' . pSql($product_sku) . '\' AND id_lang=1';
        //echo $query."\n";
        $row = Db::getInstance()->getRow($query);
        if ($row['id_product'])
            return $row['id_product'];
        else
            return false;
    }

    public static function getProductIdBySku($product_sku)
    {
        $sql = 'SELECT id_product FROM `' . _DB_PREFIX_ . 'product` WHERE reference=\'' . pSql($product_sku) . '\'';
        $res = Db::getInstance()->getRow($sql);
        if ($res['id_product'])
            return $res['id_product'];
        else
            return false;
    }
}
