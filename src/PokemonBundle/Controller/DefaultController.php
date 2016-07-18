<?php

namespace PokemonBundle\Controller;

use PokemonBundle\Entity\PokemonLocation;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="pokemon.default.index")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/search/location/{lat}/{lon}", name="pokemon.default.search.location")
     * @Template()
     */
    public function searchLocationAction($lat, $lon)
    {
        $repository = $this->getDoctrine()->getRepository('PokemonBundle:PokemonLocation');

        $query = $repository->createQueryBuilder('pl')
            ->join('pl.pokemon', 'p')
            ->getQuery();
        $results = $query->getResult();

        $resultsJSON = array();
        /** @var PokemonLocation $result */
        foreach($results as $result) {
            if (!isset($resultsJSON[$result->getPokemon()->getNumber()])) {
                $resultsJSON[$result->getPokemon()->getNumber()] = array(
                    'number'=> $result->getPokemon()->getNumber(),
                    'name' => $result->getPokemon()->getName(),
                    'color' => $this->random_color(),
                    'locations' => array()
                );
            }

            $resultsJSON[$result->getPokemon()->getNumber()]['locations'][] = array(
                'lat' => $result->getLat(),
                'lon' => $result->getLon()
            );
        }

        //var_dump($resultsJSON);
        //die();

        return array(
            'results' => $resultsJSON
        );
    }

    private function random_color_part() {
        return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
    }

     private function random_color() {
        return $this->random_color_part() . $this->random_color_part() . $this->random_color_part();
    }
}
