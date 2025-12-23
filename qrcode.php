<?php
// qrcode.php - Simple QR Code Generator
class QRCode {
    private $size;
    private $data;
    
    public function __construct($data, $size = 200) {
        $this->data = urlencode($data);
        $this->size = $size;
    }
    
    public function render() {
        $url = "https://api.qrserver.com/v1/create-qr-code/?size={$this->size}x{$this->size}&data={$this->data}";
        return $url;
    }
    
    public function display() {
        echo '<img src="' . $this->render() . '" alt="QR Code" style="width: ' . $this->size . 'px; height: ' . $this->size . 'px;">';
    }
    
    public static function generate($data, $size = 200) {
        $qrcode = new self($data, $size);
        return $qrcode->render();
    }
}
?>