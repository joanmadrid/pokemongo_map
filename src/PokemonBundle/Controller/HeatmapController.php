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
     * @Route("/{div1}/{div2}", name="pokemon.heatmap.index")
     * @Template()
     */
    public function indexAction($div1 = 9, $div2 = 18)
    {
        //900, 1800
        $sectors = $this->getSectors($div1, $div2);
        //var_dump($sectors);

        $heatPoints = array();
        foreach ($sectors as $sector) {
            $points = $this->findPointsInbound($sector[1], $sector[3]);
            $heatPoint = $this->calculateHeatPoint($points, $sector);
            if ($heatPoint) {
                $heatPoints[] = $heatPoint;
                var_dump($heatPoint);
            }
        }


        //die();
        return array(
            'sectors' => $sectors,
            'heatPoints' => $heatPoints
        );
    }

    private function getSectors($latDiv, $lonDiv)
    {
        $latBounds = array(-90, 90);
        $latRange = abs($latBounds[0]) + $latBounds[1];
        echo '<br />absolute lat: '.$latRange;
        $lonBounds = array(-180, 180);
        $lonRange = abs($lonBounds[0]) + abs($lonBounds[1]);
        echo '<br />absolute lon: '.$lonRange;

        $sectors = array();
        $latCurr = $latBounds[0];
        $lonCurr = $lonBounds[0];
        echo '<br />current lat: '.$latCurr;
        echo '<br />current lon: '.$lonCurr;

        $latInc = $latRange / $latDiv;
        $lonInc = $lonRange / $lonDiv;
        echo '<br />incr lat: '.$latInc;
        echo '<br />incr lon: '.$lonInc;

        echo '<br />===== start algorithm =====';

        for ($i = 1; $i <= $latDiv; $i++) {
            $lonCurr = $lonBounds[0];
            for ($j = 1; $j <= $lonDiv; $j++) {
                $sectors[] = array(
                    array($latCurr + $latInc, $lonCurr),
                    array($latCurr, $lonCurr),
                    array($latCurr, $lonCurr + $lonInc),
                    array($latCurr + $latInc, $lonCurr + $lonInc)
                );
                $lonCurr += $lonInc;
            }
            $latCurr += $latInc;
        }
        echo '<br />===== end algorithm =====';
        echo '<br />current lat: '.$latCurr;
        echo '<br />current lon: '.$lonCurr;

        return $sectors;
    }

    private function findPointsInbound($latlon1, $latlon2)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getDoctrine()->getRepository('PokemonBundle:PokemonLocation');

        /** @var Query $query */
        $query = $repository->createQueryBuilder('pl')
            ->where('pl.lat > :latMin')
            ->andWhere('pl.lat <= :latMax')
            ->andWhere('pl.lon > :lonMin')
            ->andWhere('pl.lon <= :lonMax')
            ->setParameters(array(
                'latMin' => $latlon1[0],
                'latMax' => $latlon2[0],
                'lonMin' => $latlon1[1],
                'lonMax' => $latlon2[1]
            ))
            ->getQuery();

        $results = $query->getArrayResult();

        return $results;
    }

    private function calculateHeatPoint($points, $sector)
    {
        $heatPoint = array();

        //calculate force
        $heatPoint['force'] = count($points);

        if ($heatPoint['force'] == 0) {
            $heatPoint = null;
        } else {
            //calculate middle
            $heatPoint['lat'] = $sector[2][0] + (($sector[3][0] - $sector[1][0]) / 2);
            $heatPoint['lon'] = $sector[2][1] - (($sector[3][1] - $sector[1][1]) / 2);
        }

        return $heatPoint;
    }
}
