<?php    
/*
Plugin Name: Pamięć podręczna dla widgetów
Description: Zapisuje wygenerowany kod HTML widgetów w cache. Konfiguracja dostępna podczas edycji poszczególnego widgetu.
Version: 1.0.0
Author: Sebastian Bort
*/

class WP_Widget_Cache {

    const transient_key = '_widget_settings';      
    
    const ttl_default = 300;
    const ttl_minimum = 5;
    
    private $settings = [];
                                   
	public function __construct() {

            add_filter('widget_update_callback', [$this, 'on_widget_update'], 10, 4);
            add_filter('widget_display_callback', [$this, 'on_widget_callback'], 10, 3);  
            
            add_action('init', [$this, 'load_settings']);
            add_action('sidebar_admin_setup', [$this, 'save_settings']);
            add_action('in_widget_form', [$this, 'display_settings'], 10, 3); 
	}      
    
    private function update_database() {
            update_option(self::transient_key, $this->settings, false); 
    }    
    
	public function on_widget_callback($instance, $object, $args) {  
    
            if(empty($object->id) || !empty($this->settings[$object->id]['excluded'])) {
                    return $instance;
            }    
            
            if(empty($this->settings[$object->id]['ttl'])) {
                    $this->settings[$object->id]['ttl'] = self::ttl_default;
            }                  
    
            if(empty($this->settings[$object->id]['cache']) || empty($this->settings[$object->id]['timestamp']) || time() - $this->settings[$object->id]['timestamp'] > $this->settings[$object->id]['ttl'] * 60) {
                
        			ob_start();
        			
                    $object->widget($args, $instance);
        			
                    $this->settings[$object->id]['cache'] = ob_get_contents();
                    $this->settings[$object->id]['timestamp'] = time();
                    
        			ob_end_clean();
                    
                    $this->update_database(); 
            }
            
            echo $this->settings[$object->id]['cache'];            
            
            return false;
    }
    
    public function on_widget_update($instance, $new_instance, $old_instance, $object) {
            
            $object_id = sanitize_text_field($object->id);
            if(empty($object_id)) {
                return $instance;
            }
            
            $this->settings[$object_id]['cache'] = null;
            $this->settings[$object_id]['timestamp'] = null;

            $this->update_database();
                        
            return $instance;            
    }
    
    public function load_settings() {
            
            $this->settings = get_option(self::transient_key);
            if(!is_array($this->settings)) {
                    $this->settings = [];
            }    
    }
    
    public function save_settings() {              
  
            $object_id = !empty($_POST['widget-id']) ? sanitize_text_field($_POST['widget-id']) : false;
            if(empty($object_id)) {
                return false;
            }
            
            if(!is_array($this->settings[$object_id])) {
                $this->settings[$object_id] = [];
            }

            $this->settings[$object_id]['excluded'] = (bool) !empty($_POST['cache_excluded']);
            $this->settings[$object_id]['ttl'] = !empty(intval($_POST['cache_ttl'])) ? (int) $_POST['cache_ttl'] : self::ttl_default;
              
            $this->update_database();               
    }
    
    public function display_settings($object, $return, $instance) {
    
           $excluded_from_cache = (bool) !empty($this->settings[$object->id]['excluded']);
           $cache_ttl = !empty(intval($this->settings[$object->id]['ttl'])) ? (int) $this->settings[$object->id]['ttl'] : self::ttl_default;     
        
           printf('<p style="font-weight:bold;">
                        Ustawienia pamięci podręcznej
                   </p>
                   <p>
                       <label>
                            <input type="checkbox" name="cache_excluded" value="1" %s> Wyłącz pamięć podręczną dla tego widgetu
                       </label>
                    </p>
                   <p>
                      <label>
                            <input style="width:80px;text-align:center;" type="number" min="%d" name="cache_ttl" value="%s"> Czas pamięci podręcznej w minutach
                      </label>
                   </p>', 
                   checked($excluded_from_cache, true, false),
                   self::ttl_minimum,
                   $cache_ttl
           );                
    }
}
new WP_Widget_Cache();

?>