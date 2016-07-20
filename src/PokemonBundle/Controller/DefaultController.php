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
     * @Route("/search/location/{lat}/{lon}", name="pokemon.default.search.location", options={"expose"=true})
     * @Template()
     */
    public function searchLocationAction($lat, $lon, $distance = 5)
    {
        $repository = $this->getDoctrine()->getRepository('PokemonBundle:PokemonLocation');

        $query = $repository->createQueryBuilder('pl')
            ->addSelect(
                '( 3959 * acos(cos(radians(' . $lat . '))' .
                '* cos( radians( pl.lat ) )' .
                '* cos( radians( pl.lon )' .
                '- radians(' . $lon . ') )' .
                '+ sin( radians(' . $lat . ') )' .
                '* sin( radians( pl.lat ) ) ) ) as distance'
            )
            ->join('pl.pokemon', 'p')
            ->having('distance < :distance')
            ->setParameter('distance', $distance)
            ->orderBy('p.tier', 'DESC')
            ->getQuery();
        $results = $query->getResult();

        $resultsJSON = array();
        /** @var PokemonLocation $result */
        foreach($results as $result) {
            if (!isset($resultsJSON[$result[0]->getPokemon()->getNumber()])) {
                $resultsJSON[$result[0]->getPokemon()->getNumber()] = array(
                    'number'=> $result[0]->getPokemon()->getNumber(),
                    'name' => $result[0]->getPokemon()->getName(),
                    'color' => $this->random_color(),
                    'tier' => $result[0]->getPokemon()->getTier(),
                    'locations' => array()
                );
            }

            $resultsJSON[$result[0]->getPokemon()->getNumber()]['locations'][] = array(
                'lat' => $result[0]->getLat(),
                'lon' => $result[0]->getLon()
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
