<?php

namespace PokemonBundle\Controller;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/heatmap")
 */
class HeatmapController extends Controller
{
    /**
     * @Route("/", name="pokemon.heatmap.index")
     * @Template()
     */
    public function indexAction()
    {
        $repository = $this->getDoctrine()->getRepository('PokemonBundle:Heatpoint');

        $query = $repository->createQueryBuilder('hp')
            ->getQuery();
        $results = $query->getResult();


        //die();
        return array(
            'heatPoints' => $results
        );
    }
}
