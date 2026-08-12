<?php
namespace Database\Seeders;

use App\Models\Attraction;
use Illuminate\Database\Seeder;

class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $attractions = [
            // Cebu City
            ['destination' => 'Cebu City', 'name' => "Magellan's Cross", 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A Christian cross planted by Portuguese and Spanish explorers as ordered by Ferdinand Magellan upon arriving in Cebu in 1521.'],
            ['destination' => 'Cebu City', 'name' => 'Temple of Leah', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'The "Taj Mahal of Cebu" — a grand hilltop temple built as a symbol of undying love, with panoramic city views.'],
            ['destination' => 'Cebu City', 'name' => 'Basilica del Santo Niño', 'category' => 'Culture', 'rating' => 4.7,
                'description' => 'The oldest Roman Catholic church in the Philippines, home to the revered Santo Niño de Cebú image.'],
            ['destination' => 'Cebu City', 'name' => 'Tops Lookout', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A mountaintop viewing deck offering sweeping sunset views over Cebu City and the strait beyond.'],

            // Boracay
            ['destination' => 'Boracay', 'name' => 'White Beach', 'category' => 'Nature', 'rating' => 4.7,
                'description' => "Boracay's world-famous stretch of powdery white sand, lined with resorts, bars, and water activities."],
            ['destination' => 'Boracay', 'name' => 'Puka Shell Beach', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A quieter, less-crowded beach on the island known for its puka shells and laid-back atmosphere.'],
            ['destination' => 'Boracay', 'name' => 'Mount Luho', 'category' => 'Adventure', 'rating' => 4.2,
                'description' => "The highest point on Boracay Island, reachable by ATV or cable car, with a 360-degree viewing deck."],

            // Davao City
            ['destination' => 'Davao City', 'name' => 'Philippine Eagle Center', 'category' => 'Nature', 'rating' => 4.6,
                'description' => 'A wildlife conservation center dedicated to breeding and protecting the critically endangered Philippine eagle.'],
            ['destination' => 'Davao City', 'name' => "People's Park", 'category' => 'Culture', 'rating' => 4.3,
                'description' => "An urban park featuring sculptures and exhibits celebrating Mindanao's diverse indigenous cultures."],
            ['destination' => 'Davao City', 'name' => 'Eden Nature Park', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A mountain resort and garden park offering cool climate, zip-lines, and views over Davao Gulf.'],

            // Dumaguete
            ['destination' => 'Dumaguete', 'name' => 'Rizal Boulevard', 'category' => 'Culture', 'rating' => 4.5,
                'description' => "A historic tree-lined waterfront promenade and the social heart of Dumaguete's city center."],
            ['destination' => 'Dumaguete', 'name' => 'Apo Island', 'category' => 'Nature', 'rating' => 4.8,
                'description' => 'A marine sanctuary famed for snorkeling and diving with sea turtles amid protected coral reefs.'],
            ['destination' => 'Dumaguete', 'name' => 'Casaroro Falls', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A scenic waterfall in nearby Valencia reached via a steep staircase through lush forest.'],

            // Iloilo City
            ['destination' => 'Iloilo City', 'name' => 'Miagao Church', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A UNESCO World Heritage baroque church known for its ornate façade blending European and local motifs.'],
            ['destination' => 'Iloilo City', 'name' => 'Molo Church', 'category' => 'Culture', 'rating' => 4.5,
                'description' => 'A Gothic-Renaissance church nicknamed the "Feminist Church" for its all-female saint statues.'],
            ['destination' => 'Iloilo City', 'name' => 'Iloilo River Esplanade', 'category' => 'Nature', 'rating' => 4.4,
                'description' => 'A riverside promenade popular for walking, jogging, and evening views along the Iloilo River.'],

            // Manila
            ['destination' => 'Manila', 'name' => 'Intramuros', 'category' => 'Culture', 'rating' => 4.5,
                'description' => "Manila's historic walled city from the Spanish colonial era, home to Fort Santiago and San Agustin Church."],
            ['destination' => 'Manila', 'name' => 'Rizal Park', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A sprawling historic urban park honoring national hero José Rizal, near Manila Bay.'],
            ['destination' => 'Manila', 'name' => 'National Museum of the Philippines', 'category' => 'Culture', 'rating' => 4.6,
                'description' => 'A leading art and natural history museum showcasing Filipino heritage and archaeological finds.'],

            // Cagayan de Oro
            ['destination' => 'Cagayan de Oro', 'name' => 'CDO River', 'category' => 'Adventure', 'rating' => 4.5,
                'description' => 'The Cagayan de Oro River, famous nationwide for its whitewater rafting rapids.'],
            ['destination' => 'Cagayan de Oro', 'name' => 'Gardens of Malasag Eco-Tourism Village', 'category' => 'Nature', 'rating' => 4.3,
                'description' => 'A mountain eco-park with native huts, gardens, and views over Macajalar Bay.'],
            ['destination' => 'Cagayan de Oro', 'name' => 'Divine Mercy Shrine', 'category' => 'Culture', 'rating' => 4.4,
                'description' => 'A hilltop religious shrine and pilgrimage site overlooking the city.'],

            // Siquijor
            ['destination' => 'Siquijor', 'name' => 'Cambugahay Falls', 'category' => 'Nature', 'rating' => 4.7,
                'description' => 'A multi-tiered turquoise waterfall with natural rope swings, one of the island\'s top draws.'],
            ['destination' => 'Siquijor', 'name' => 'Salagdoong Beach', 'category' => 'Nature', 'rating' => 4.5,
                'description' => 'A beach known for its clear water and cliff-diving platforms into the sea below.'],
            ['destination' => 'Siquijor', 'name' => 'Old Enchanted Balete Tree', 'category' => 'Nature', 'rating' => 4.2,
                'description' => 'A centuries-old balete tree wrapped in local folklore, with a fish spa in its spring-fed pool.'],
        ];

        foreach ($attractions as $a) {
            Attraction::firstOrCreate(
                ['name' => $a['name'], 'destination' => $a['destination']],
                $a
            );
        }
    }
}
