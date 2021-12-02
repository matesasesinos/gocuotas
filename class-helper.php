<?php

class GoCuotas_Helper {

    private static $instance;

    private function __construct() {
        add_action('admin_init', [$this,'uploadIcon']);
    }

    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //logger
    public static function go_log($log, $save)
    {
        $logg = fopen(__DIR__ . '/logs/' . $log, 'w');
        fwrite($logg, $save);
        fclose($logg);
    }

    public function uploadIcon()
    {
        if(isset($_FILES['woocommerce_gocuotas_iconfile']) && $_FILES['woocommerce_gocuotas_iconfile']['size'] > 0) {
            if($_FILES['woocommerce_gocuotas_iconfile']['size'] > 20000) {
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-error is-dismissible"><p>'.__('El icono no puede pesar mas de 20KB.', 'gocuotas').'</p></div>';
                });
                return;
            }

            $tmp_file = $_FILES['woocommerce_gocuotas_iconfile']['tmp_name'];

            $type = mime_content_type($tmp_file);
     

            if($type != 'image/png') {
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-error is-dismissible"><p>'.__('El icono debe ser PNG.', 'gocuotas').'</p></div>';
                });
                return;
            }

            list($width) = getimagesize($tmp_file);

            if($width > 90) {
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-error is-dismissible"><p>'.__('El icono debe tener un ancho menor a 90px.', 'gocuotas').'</p></div>';
                });
                return;
            }

            $upload = wp_upload_bits( $_FILES['woocommerce_gocuotas_iconfile']['name'], null, file_get_contents($tmp_file));
            update_option( 'go_cuotas_icon', $upload['url'], true );

            add_action('admin_notices', function(){
                echo '<div class="notice notice-success is-dismissible"><p>'.__('El icono se cambio correctamente.', 'gocuotas').'</p></div>';
            });
        }
    }

}

GoCuotas_Helper::getInstance();