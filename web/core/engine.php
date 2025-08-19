<?php
class TemplateEngine {
    private $templatesPath;
    private $data = [];
    private $db;

    public function __construct($templatesPath, $db = null) {
        $this->templatesPath = rtrim($templatesPath, '/') . '/';
        $this->db = $db;
    }

    public function assign($key, $value) {
        $this->data[$key] = $value;
    }

    public function append($key, $value) {
        if (isset($this->data[$key])) {
            array_push($this->data[$key], $value);
        }else{
            $this->data[$key] = [$value];
        }
    }

    public function render($template, $localData = []) {
        $data = array_merge($this->data, $localData);
        
        // Extract variables to make them available in the template
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the template file
        include $this->templatesPath . $template;
        
        // Get the contents of the buffer and clean it
        $output = ob_get_clean();
        
        return $output;
    }

    public function renderLayout($template, $content, $localData = []) {
        $this->assign('content', $content);
        return $this->render($template, $localData);
    }
}
?>