<?php
/**
 * Plugin Name: Chevereto古腾堡区块
 * Plugin URI: https://hnink.com
 * Description: 为古腾堡编辑器添加Chevereto图床上传功能的区块插件
 * Version: 1.0.0
 * Author: Hudson
 * License: GPL v2 or later
 * Text Domain: chevereto-block
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('CHEVERETO_BLOCK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHEVERETO_BLOCK_PLUGIN_PATH', plugin_dir_path(__FILE__));

class CheveretoBlockPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_ajax_chevereto_upload', array($this, 'handle_ajax_upload'));
        add_action('wp_ajax_nopriv_chevereto_upload', array($this, 'handle_ajax_upload'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * 注册区块
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('chevereto-block/image-uploader', array(
            'editor_script' => 'chevereto-block-editor',
            'editor_style' => 'chevereto-block-editor-style',
            'style' => 'chevereto-block-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'imageUrl' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'imageId' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'title' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'description' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => 'center'
                ),
                'width' => array(
                    'type' => 'number',
                    'default' => 500
                )
            )
        ));
    }
    
    /**
     * 加载编辑器资源
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'chevereto-block-editor',
            CHEVERETO_BLOCK_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'chevereto-block-editor-style',
            CHEVERETO_BLOCK_PLUGIN_URL . 'assets/css/block-editor.css',
            array(),
            '1.0.0'
        );
        
        // 传递数据到JavaScript
        wp_localize_script('chevereto-block-editor', 'cheveretoBlock', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chevereto_upload_nonce'),
            'apiUrl' => 'https://img.i18.net/api/1/upload',
            'hasApiKey' => !empty(get_option('chevereto_api_key'))
        ));
    }
    
    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'chevereto-block-style',
            CHEVERETO_BLOCK_PLUGIN_URL . 'assets/css/block-style.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * 渲染区块
     */
    public function render_block($attributes) {
        $image_url = $attributes['imageUrl'];
        $title = $attributes['title'];
        $description = $attributes['description'];
        $alignment = $attributes['alignment'];
        $width = $attributes['width'];
        
        if (empty($image_url)) {
            return '<div class="chevereto-block-placeholder">请在编辑器中上传图片</div>';
        }
        
        $align_class = 'align' . $alignment;
        
        $output = sprintf(
            '<div class="chevereto-block-wrapper %s">
                <figure class="chevereto-block-figure" style="max-width: %dpx;">
                    <img src="%s" alt="%s" class="chevereto-block-image" />
                    %s
                    %s
                </figure>
            </div>',
            esc_attr($align_class),
            intval($width),
            esc_url($image_url),
            esc_attr($title),
            !empty($title) ? '<figcaption class="chevereto-block-title">' . esc_html($title) . '</figcaption>' : '',
            !empty($description) ? '<div class="chevereto-block-description">' . esc_html($description) . '</div>' : ''
        );
        
        return $output;
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            'Chevereto图床设置',
            'Chevereto图床',
            'manage_options',
            'chevereto-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * 初始化设置
     */
    public function settings_init() {
        register_setting('chevereto_settings', 'chevereto_api_key');
        register_setting('chevereto_settings', 'chevereto_api_url');
        
        add_settings_section(
            'chevereto_settings_section',
            'API设置',
            array($this, 'settings_section_callback'),
            'chevereto_settings'
        );
        
        add_settings_field(
            'chevereto_api_key',
            'API密钥',
            array($this, 'api_key_field_callback'),
            'chevereto_settings',
            'chevereto_settings_section'
        );
        
        add_settings_field(
            'chevereto_api_url',
            'API地址',
            array($this, 'api_url_field_callback'),
            'chevereto_settings',
            'chevereto_settings_section'
        );
    }
    
    /**
     * 设置页面
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Chevereto图床设置</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('chevereto_settings');
                do_settings_sections('chevereto_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>使用说明</h2>
                <p>1. 请先在上方填入您的Chevereto API密钥和API地址</p>
                <p>2. 保存设置后，您就可以在古腾堡编辑器中使用"Chevereto图片上传"区块</p>
                <p>3. 该区块支持拖拽上传、点击上传等多种方式</p>
                <p>4. 默认API地址：https://img.i18.net/api/1/upload</p>
            </div>
        </div>
        <?php
    }
    
    public function settings_section_callback() {
        echo '<p>请配置您的Chevereto图床API信息</p>';
    }
    
    public function api_key_field_callback() {
        $value = get_option('chevereto_api_key');
        echo '<input type="password" name="chevereto_api_key" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">您的Chevereto API密钥，可在用户设置中获取</p>';
    }
    
    public function api_url_field_callback() {
        $value = get_option('chevereto_api_url', 'https://img.i18.net/api/1/upload');
        echo '<input type="url" name="chevereto_api_url" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">Chevereto API上传地址</p>';
    }
    
    /**
     * 处理AJAX上传
     */
    public function handle_ajax_upload() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chevereto_upload_nonce')) {
            wp_die('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('upload_files')) {
            wp_die('权限不足');
        }
        
        $api_key = get_option('chevereto_api_key');
        $api_url = get_option('chevereto_api_url', 'https://img.i18.net/api/1/upload');
        
        if (empty($api_key)) {
            wp_send_json_error('请先在设置页面配置API密钥');
        }
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error('没有文件被上传');
        }
        
        $file = $_FILES['file'];
        
        // 发送到Chevereto API
        $response = $this->upload_to_chevereto($file, $api_key, $api_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * 上传到Chevereto
     */
    private function upload_to_chevereto($file, $api_key, $api_url) {
        $boundary = wp_generate_password(12, false);
        
        $headers = array(
            'X-API-Key' => $api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        );
        
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="source"; filename="' . $file['name'] . '"' . "\r\n";
        $body .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
        $body .= file_get_contents($file['tmp_name']) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['status_code']) && $data['status_code'] == 200) {
            return $data;
        } else {
            return new WP_Error('upload_failed', '上传失败：' . (isset($data['error']['message']) ? $data['error']['message'] : '未知错误'));
        }
    }
    
    /**
     * 注册REST API路由
     */
    public function register_rest_routes() {
        register_rest_route('chevereto-block/v1', '/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_upload'),
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ));
    }
    
    /**
     * REST API上传处理
     */
    public function rest_upload($request) {
        $files = $request->get_file_params();
        
        if (empty($files['file'])) {
            return new WP_Error('no_file', '没有文件被上传', array('status' => 400));
        }
        
        $api_key = get_option('chevereto_api_key');
        $api_url = get_option('chevereto_api_url', 'https://img.i18.net/api/1/upload');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', '请先配置API密钥', array('status' => 400));
        }
        
        $response = $this->upload_to_chevereto($files['file'], $api_key, $api_url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return rest_ensure_response($response);
    }
}

// 初始化插件
new CheveretoBlockPlugin();

// 激活插件时创建默认设置
register_activation_hook(__FILE__, function() {
    if (!get_option('chevereto_api_url')) {
        add_option('chevereto_api_url', 'https://img.i18.net/api/1/upload');
    }
});
?>