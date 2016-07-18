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
            $resultsJSON['pokemons'][$result->getPokemon()->getId()] = array(
                'id'=> $result->getPokemon()->getId(),
                'name' => $result->getPokemon()->getName()
            );

            $resultsJSON['locations'][] = array(
                'pokemon_id' => $result->getPokemon()->getId(),
                'pokemon_name' => $result->getPokemon()->getName(),
                'lat' => $result->getLat(),
                'lon' => $result->getLon()
            );
        }

        return array(
            'results' => $resultsJSON
        );
    }
}
