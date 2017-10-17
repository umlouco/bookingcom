<?php

namespace MarioFlores\Bookingcom;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class Bookingcom {

    public $client;
    private $headers;
    public $location;
    public $country;
    public $current_page;
    public $next_page;
    public $errors = array();

    private function setGuzzle() {
        $this->setHeaders();
        $this->client = new Client([
            'headers' => $this->headers,
            'timeout' => 180,
            'cookies' => new \GuzzleHttp\Cookie\CookieJar,
            'http_errors' => false
        ]);
    }
    
    private function setHeaders($headers = null) {
        if (is_null($headers)) {
            $this->header = [
                'User-Agent' => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0",
                'Accept-Language' => "en-US,en;q=0.5"
            ];
        } else {
            $this->headers = $headers;
        }
    }
    
    public function searchUrl() {
        if (empty($this->dest_id) or empty($this->dest_type)) {
            throw new \RuntimeException("Can not search, location has not been set.");
        }
        return "https://www.booking.com/searchresults.html?dest_id=".$this->dest_id."&dest_type=".$this->dest_type."&dtdisc=0&group_adults=1&group_children=0&inac=0&index_postcard=0&label_click=undef&no_rooms=1&offset=0&postcard=0&raw_dest_type=region&room1=A&sb_price_type=total&search_selected=1&src=index&src_elem=sb&ss=&ss_all=0&ss_raw=&ssb=empty&sshis=0";
    }

    public function search($dest_id, $dest_type) {
        $this->dest_type = $dest_type; 
        $this->dest_id = $dest_id; 
        $this->setGuzzle();
        $response = $this->client->request("GET", $this->searchUrl());
        return $response->getBody()->getContents();
    }

    public function getNextPage($html) {
        $crawler = new Crawler($html);
        if($crawler->filter('.x-list > .current')->count() == 0){
            return false; 
        }
        if ($crawler->filter('.x-list > .current')->nextAll()->count() == 0) {
            return false;
        } 
        $next = $crawler->filter('.x-list > .current')->nextAll()->eq(0)->filter('a')->attr('href');
        if (empty($next)) {
            throw new RuntimeException("Next links is empty");
        }
        return $next;
    }

    public function getHotels($html) {
        $crawler = new Crawler($html);
        $links = $crawler->filter('.hotel_name_link')->each(function(Crawler $node, $i) {
            $link = $node->attr('href'); 
            if(strpos($link, 'booking.com') === FALSE){
                $link = 'https://booking.com'.trim(str_replace(' ', '', $link)); 
            }
            return $link;
        });
        return $links;
    }

    public function listAllHotels($html) {
        $hotel_links = array();
        $links = $this->getHotels($html);
        $hotel_links = array_merge($hotel_links, $links);
        $next = $this->getNextPage($html);
        while ($next) {
            try {
                sleep(5);
                $response = $this->client->request("GET", $next);
                $html = $response->getBody()->getContents();
                $links = $this->getHotels($html);
                $hotel_links = array_merge($hotel_links, $links);
                $next = $this->getNextPage($html);
            } catch (Exception $ex) {
                $this->errors[] = $ex->getMessage();
            }
        }

        return $hotel_links;
    }

    function getHotel($link) {
        $this->setGuzzle();
        try {
            $response = $this->client->request("GET", $link);
            if ($response->getStatusCode() != 200) {
                return false;
            }
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            $hotel['nome'] = trim($crawler->filter('#hp_hotel_name')->text());
            $hotel['morada'] = trim($crawler->filter(".hp_address_subtitle")->text());
            $hotel['url'] = "https://www.booking.com" . $link;
            preg_match("/atnm: '(.*?)\',/s", $crawler->html(), $matches);
            $hotel['tipo'] = $matches[1];
            $hotel['origem'] = 'booking';
            preg_match("/hotel_id: '(.*?)',/s", $crawler->html(), $matches);
            $hotel['id_externo'] = $matches[1];
            preg_match("/city_name: '(.*?)',/s", $crawler->html(), $matches);
            $hotel['cidade'] = $matches[1];
            preg_match("/\"ratingValue\" \: (.*?),/s", $crawler->html(), $matches);
            $hotel['rating'] = $matches[1];
            preg_match("/booking.env.b_map_center_latitude = (.*?);/s", $crawler->html(), $matches);
            $hotel['lat'] = $matches[1];
            preg_match("/booking.env.b_map_center_longitude = (.*?);/s", $crawler->html(), $matches);
            $hotel['long'] = $matches[1];
            preg_match("/region_name: '(.*?)',/s", $crawler->html(), $matches);
            $hotel['regiao'] = $matches[1];
            preg_match("/name=\"maxrooms\" value=\"(.*?)\"/s", $crawler->html(), $matches);
            $hotel['quartos'] = $matches[1];
        } catch (Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
        return $hotel;
    }

    function getPhotos($link) {
        $this->setGuzzle();
        try {
            $response = $this->client->request("GET", $link);
            if ($response->getStatusCode() != 200) {
                return false;
            }
            $html = $response->getBody()->getContents();
            preg_match("/hotelPhotos\: \[(.*?)\]/s", $html, $matches);
            if (!empty($matches)) {
                preg_match_all("/large_url\: \'(.*?)\'/s", $matches[1], $links);
                if (!empty($links['1'])) {
                    $json = json_decode($matches[1]);
                    foreach ($links[1] as $url) {
                        $photos[] = $url;
                    }
                }
            }
        } catch (Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
        return $photos;
    }

    function getPrice($link) {
        $this->setGuzzle();
        try {
            $response = $this->client->request("GET", $link);
            $html = $response->getBody()->getContents();

            $strings = preg_match('/window.utag_data \= (.*?);/s', $html, $matches);
            if (!empty($matches[1])) {
                $fields = explode(',', $matches[1]);
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        if (strpos($field, 'ttv:') > 0) {
                            $price = explode('\'', $field);
                            return $price[1];
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
    }
}
