Bookingcom scrape
=================

Bookingcom scrape is a tool to gather information about properties in the well known site booking.com

```php 
use MarioFlores\Bookingcom\Bookingcom; 

$booking = new Bookingcom;

/**
 * Get the first search result page
 * 
 * use parameters from original url on booking.com site 
 * 
 * search('dest_id', 'dest_type') 
 */

$first_search_results = $booking->search('3343', 'region');  

//fetch all individual hotel links form serach result 

$all_links = $booking->listAllHotels($first_search_results); 

var_dump($all_links); 

//fetch data from one hotel using link 

$hotel = $booking->getHotel($all_links[0]); 

//fetch url of photos 

$photos = $booking->getPhotos($all_links[0]); 

//fetch price 

$price = $booking->getPrice($all_links[0]); 