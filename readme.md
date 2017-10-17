Bookingcom scrape
=================

Bookingcom scrape is a tool to gather information about properties in the well known site booking.com

```php 
use MarioFlores\Bookingcom\Bookingcom; 

$booking = new Bookingcom;

//search for hotels and get a list of links to individual pages 

$first_search_results = $booking->search('São Miguel', 'Portugal'); 

//fetch all individual hotel links form serach result 

$all_links = $booking->listAllHotels($first_search_results); 

//fetch data from one hotel using link 

$hotel = $booking->getHotel($all_links[0]); 

//fetch url of photos 

$photos = $booking->getPhotos($all_links[0]); 

//fetch price 

$price = $booking->getPrice($all_links[0]); 