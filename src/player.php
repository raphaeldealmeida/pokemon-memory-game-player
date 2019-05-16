<?php
include('vendor/autoload.php');

use GifCreator\GifCreator;
use function PHPSTORM_META\elementType;
use Symfony\Component\Panther\Client;

class Player {
    protected $spanSequence = 0;
    public function execute()
    {
        $client = Client::createChromeClient(
            null
            ,
            [
                // '--headless',
                'window-size=1200,2100',
                '--disable-gpu',
            ]
        );

        $crawler = $client->request('GET','https://vue-pokemon-memory-game.vinicius73.dev/');

        //$client->executeScript( "return document.querySelector('div.buttons.has-addons a:last-child').click()");
        $client->executeScript( "return document.querySelector('div.buttons.has-addons a:nth-child(1)').click()");
        array_map('unlink', glob(__DIR__ . "/snap/*"));
        $this->snap($client);

        $cards = $crawler->filter('.card');
        $count = count($cards);
        $map = array_map(function(){ return null;}, range(0,$count - 1));

        $reveal = function (&$map, $position = false) use ($count, $client){
            if($position === false){
                $hiddenCards = array_filter($map, function($card) {  return is_null($card); });
                if (!count($hiddenCards)) {
                    echo "Array empty", PHP_EOL;
                    return false;
                }
                $position = array_rand($hiddenCards);
            }
            //usleep(500000);
            $client->executeScript( "return document.querySelectorAll('.card figure')[arguments[0]].click()", [ $position ] );
            usleep(500000);
            $map[ $position ] = $client->executeScript( "return document.querySelectorAll('.card figure')[arguments[0]].style.backgroundImage", [ $position ] );

            if($map[ $position ] == 'url("/img/1x1.png")'){
                $map[ $position ] = null;
                echo "Bad image position: {$position}", PHP_EOL;
                return false;
            }

            $this->snap($client);
            echo "Position: {$position} - {$map[ $position ]}", PHP_EOL;
            return $position;
        };

        for($i = 0; $i <= 50; $i++){
            $selected = $reveal($map);
            $searchedPokemon =	$map[$selected];
            $tempMap = $map;
            unset($tempMap[$selected]);
            $pair = array_search($searchedPokemon, $tempMap);
            $reveal($map, $pair);

            list( $match, $map ) = $this->isFound( $crawler, $map, $searchedPokemon, $client );
            $arrayDuplicate = array_unique( array_diff_assoc( $map, array_unique( $map ) ) );
            if ($duplicated = array_pop($arrayDuplicate)){

                echo 'Duplicated!' . PHP_EOL;

                $duplicatedKeys = array_keys($map, $duplicated);

                $reveal($map, array_pop($duplicatedKeys));
                $reveal($map, array_pop($duplicatedKeys));

                list( $match, $map ) = $this->isFound( $crawler, $map, $searchedPokemon, $client );
            }
            if($crawler->filterXPath("//p[text()[contains(.,'You got them all!!')]]")->count()){

                $this->snap($client);
                echo 'I won!' . PHP_EOL;
                break;
            }
        }
        var_dump($map);
        $this->buildAnimatedGif();
    }


    function snap(Client $client){
        $this->spanSequence++;
        $client->takeScreenshot(__DIR__ .  "/snap/{$this->spanSequence}.png");

    }

    /**
     * @param \Symfony\Component\Panther\DomCrawler\Crawler $crawler
     * @param array $map
     * @param $searchedPokemon
     * @param Client $client
     *
     * @return array
     */
    protected function isFound( \Symfony\Component\Panther\DomCrawler\Crawler $crawler, array $map, $searchedPokemon, Client $client ) {
        $match = $crawler->filterXPath( "//p[text()[contains(.,'You find')]]" ); //should be nodeElement maybe a DomCrawler bug
        if ( $match->count() ) {
            $map = array_diff( $map, [ $searchedPokemon ] );
            echo $match->text() . PHP_EOL;
            $this->snap( $client );
        }
        return array( $match, $map );
    }

    public function buildAnimatedGif(){
        var_dump($frames = glob(__DIR__ . "/snap/*.png"));

        $duration = array_map(function(){ return 100;}, range(0,count($frames) - 1));
        $gc = new GifCreator();
        file_put_contents(__DIR__ . "/snap/animation.gif", $gc->create($frames, $duration));

    }


}

$player = new Player();
$player->execute();