<?php



class routeMapStaticImage {

    const StreetMapURL = "https://nominatim.openstreetmap.org/search?q={SEARCH}&format=json&addressdetails=1";

    // Geometry URL for directions
    const GEOURL = "https://api.mapbox.com/directions/v5/mapbox/driving/{locations}?access_token={token}";

    // Static map URL for displaying the route
    const MAPURL = "https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/{PINS},path-5+{LINE_COLOR}-{LINE_THICK}({geometry})/{PANAROMIC}/{SIZE}?access_token={token}";

    private $tokens = [];

    private $geometry = "";
    private $lat1, $lon1, $lat2,$lon2;

    private $routes = [];

    private $waterMark = "";
    private WaterMarkPosition $waterMarkPosition = WaterMarkPosition::LEFT_TOP;

    private $fontPath = "";
    private $fontSize = 16;

    private $size = "500x300";
    private $panaromic = 'auto';

    private $defaultLineColor = "f44";
    private $defaultLineThick = "0.5";

    private $waterMarkColor = [ 255, 255, 255];
    private $waterMarkBorderColor = [ 0, 0, 0];
    private $waterMarkShadowColor = [ 128, 128, 128];

    private $shadowOffset = 1;
    private $borderOffset = 2;

    public routeMapStaticImageLine $line;

    
    private $locations = [];
    
    private $errors = [];

    public function __construct($tokenlist) {

        $this->line = new routeMapStaticImageLine($this->defaultLineColor, $this->defaultLineThick);

        if(is_array($tokenlist)){
            $this->tokens = $tokenlist;
        }else{
            $this->tokens  = [ $tokenlist ];
        }
    }

    public function setWaterMark( $text ){
        $this->waterMark = $text;
    }
    public function setWaterMarkPosition(WaterMarkPosition $pos){
        $this->waterMarkPosition = $pos;
    }
    public function setFontPath( $path ){
        $this->fontPath = $path;
    }
    public function setFontSize( $size ){
        $this->fontSize = 16;
    }

  

    public function addLocation($location, $char, $backgroundColor){
        $this->locations[] = [$location, $char, $backgroundColor];
    }
    public function addRoute($lat, $lon, $char, $backgroundColor){
        $this->_addRoute(new routeMapStaticImagePin("", $lat, $lon, $char, $backgroundColor));
    }
    private function _addRoute(routeMapStaticImagePin $pin){
        $this->routes[] = $pin;
    }

    public function save($savePath){
        try{
            if($this->locations):
                foreach($this->locations as $item):
                    $result = $this->searchLatLon($item[0]);
                    $this->_addRoute(new routeMapStaticImagePin($item[0], $result["lat"], $result["lon"], $item[1], $item[2]));
                endforeach;
            endif;

            $geo_response = file_get_contents($this->getGeoUrl());
            
            $geo_data = json_decode($geo_response, true);
            $this->geometry = urlencode($geo_data['routes'][0]['geometry']);
          
            $save_to = $savePath;
            $ch = curl_init( $this->getMapUrl() );
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
            //curl_setopt($ch, CURLOPT_NOBODY, true); 
            $response = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $content_type = null;
            if (preg_match('/Content-Type:\s*([^;]+)/i', $header, $matches)) {
                $content_type = trim($matches[1]);
            }
            curl_close($ch);

            if ($response === false) {
                throw new Exception("CURL Hatası: " . curl_error($ch));
            }

            if ($content_type === 'application/json') :
                if(file_exists($save_to)){
                    unlink($save_to);
                }
                throw new Exception($body);
            else:
                file_put_contents($save_to, $body);
            endif;

            if(!empty($this->waterMark)):

                if(empty($this->fontPath)):
                    throw new Exception('font file path is required');
                endif;


                $img = imagecreatefrompng($save_to);

                $text_color = imagecolorallocate($img, $this->waterMarkColor[0], $this->waterMarkColor[1], $this->waterMarkColor[2]); 
                $border_color = imagecolorallocate($img, $this->waterMarkBorderColor[0], $this->waterMarkBorderColor[1], $this->waterMarkBorderColor[2]); 
                $shadow_color = imagecolorallocate($img, $this->waterMarkShadowColor[0], $this->waterMarkShadowColor[1], $this->waterMarkShadowColor[2]); 
                $font_path = $this->fontPath;
                $font_size = $this->fontSize; 
                $text = $this->waterMark;

                $shadow_offset = $this->shadowOffset; 
                $border_offset = $this->borderOffset;

                $img_width = imagesx($img);
                $img_height = imagesy($img);

                switch($this->waterMarkPosition):
                    case WaterMarkPosition::LEFT_TOP:
                        // Gölge ekle
                        imagettftext($img, $font_size, 0, 10 + $shadow_offset, 30 + $shadow_offset, $shadow_color, $font_path, $text);
                
                        // Metnin etrafına siyah sınır ekle (beyaz metnin etrafında)
                        
                        imagettftext($img, $font_size, 0, 10 - $border_offset, 30 - $border_offset, $border_color, $font_path, $text); // Üst sol
                        imagettftext($img, $font_size, 0, 10 + $border_offset, 30 - $border_offset, $border_color, $font_path, $text); // Sağ üst
                        imagettftext($img, $font_size, 0, 10 - $border_offset, 30 + $border_offset, $border_color, $font_path, $text); // Sol alt
                        imagettftext($img, $font_size, 0, 10 + $border_offset, 30 + $border_offset, $border_color, $font_path, $text); // Sağ alt
                
                        // Beyaz metni ekle
                        imagettftext($img, $font_size, 0, 10, 30, $text_color, $font_path, $text);
                        break;
                        
                        case WaterMarkPosition::LEFT_CENTER:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, 10 + $shadow_offset, $img_height / 2 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height / 2 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height / 2 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, 10, $img_height / 2, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::LEFT_RIGHT:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, 10 + $shadow_offset, $img_height - 30 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height - 30 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height - 30 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height - 30 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height - 30 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, 10, $img_height - 30, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::CENTER_LEFT:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, 10 + $shadow_offset, $img_height / 2 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height / 2 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height / 2 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, 10, $img_height / 2, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::CENTER:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $shadow_offset, ($img_height - $text_height) / 2 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 - $border_offset, ($img_height - $text_height) / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $border_offset, ($img_height - $text_height) / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 - $border_offset, ($img_height - $text_height) / 2 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $border_offset, ($img_height - $text_height) / 2 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2, ($img_height - $text_height) / 2, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::CENTER_RIGHT:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $shadow_offset, ($img_height - $text_height) / 2 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width - $border_offset, ($img_height - $text_height) / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $border_offset, ($img_height - $text_height) / 2 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width - $border_offset, ($img_height - $text_height) / 2 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $border_offset, ($img_height - $text_height) / 2 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width, ($img_height - $text_height) / 2, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::BOTTOM_LEFT:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, 10 + $shadow_offset, $img_height - 10 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 - $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, 10 + $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, 10, $img_height - 10, $text_color, $font_path, $text);
                            break;
                        case WaterMarkPosition::BOTTOM_CENTER:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $shadow_offset, $img_height - 10 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 - $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 - $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2 + $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, ($img_width - $text_width) / 2, $img_height - 10, $text_color, $font_path, $text);
                            break;
                            
                        case WaterMarkPosition::BOTTOM_RIGHT:
                            
                            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
                            $text_width = $bbox[2] - $bbox[0];
                            $text_height = $bbox[7] - $bbox[1];
                    
                            // Gölge ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $shadow_offset, $img_height - 10 + $shadow_offset, $shadow_color, $font_path, $text);
                    
                            // Metnin etrafına siyah sınır ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width - $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $border_offset, $img_height - 10 - $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width - $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width + $border_offset, $img_height - 10 + $border_offset, $border_color, $font_path, $text);
                    
                            // Beyaz metni ekle
                            imagettftext($img, $font_size, 0, $img_width - 10 - $text_width, $img_height - 10, $text_color, $font_path, $text);
                            break;

                endswitch;
                
                imagepng($img, $save_to);
                imagedestroy($img);
            endif;

            return true;
        }catch(\Exception $e){
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    private function searchLatLon($search){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->getStreetMapURL($search),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Cache-Control: no-cache, no-store, must-revalidate', // Prevent caching
            'Pragma: no-cache', // For HTTP/1.0 compatibility
            'Expires: 0', // Expire immediately
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);

        if (empty($data)) {
            throw new Error("Lat, lng not found");
        }
        return $data[0];
    }
    private function getGeoUrl() {
        $locations = array_map(function($route){
            return sprintf("%s,%s",$route->lon,$route->lat);
        },$this->routes);

        return str_replace(
            ["{locations}","{token}"],
            [
                implode(";",$locations),
                $this->tokens[array_rand($this->tokens)]
            ],
            self::GEOURL
        );
    }


    private function getMapUrl() {
        $pins = array_map(function($route){
            return sprintf("pin-s-%s+%s(%s,%s)",($route->char),$route->backgroundColor, $route->lon,$route->lat);
        },$this->routes);
        //pin-s-a+9ed4bd({lat,lon}),pin-s-b+000({lat,lon2})
        return str_replace(
            ["{PINS}","{token}","{geometry}", "{SIZE}", "{PANAROMIC}","{LINE_COLOR}","{LINE_THICK}"],
            [
                implode(",",$pins),
                $this->tokens[array_rand($this->tokens)],
                $this->geometry,
                $this->size,
                $this->panaromic,
                $this->line->color,
                $this->line->thick
            ],
            self::MAPURL
        );
    }
    private function getStreetMapURL($search){
        return str_replace("{SEARCH}", urlencode($search), self::StreetMapURL);
    }

    public function getTokens() {
        return $this->tokens;
    }

    public function getErrors(){
        return $this->errors;
    }
}

enum WaterMarkPosition{
    case LEFT_TOP;
    case LEFT_CENTER;
    case LEFT_RIGHT;
    case CENTER_LEFT;
    case CENTER;
    case CENTER_RIGHT;
    case BOTTOM_LEFT;
    case BOTTOM_CENTER;
    case BOTTOM_RIGHT;
}


class routeMapStaticImagePin{
    public $title;
    public $lat, $lon;
    public $char = "";
    public $backgroundColor;

    public function __construct($title, $lat, $lon, $char, $backgroundColor){
        $this->title = $title;
        $this->lat = $lat;
        $this->lon = $lon;
        $this->char = $char;
        $this->backgroundColor = $backgroundColor;
    }
}

class routeMapStaticImageLine{
    public $color;
    public $thick;

    public function __construct($color, $thick){
        $this->color = $color;
        $this->thick = $thick;
    }
    public function setColor($color){
        $this->color = $color;
    }
    public function setThick($thick){
        $this->thick = $thick;
    }
}