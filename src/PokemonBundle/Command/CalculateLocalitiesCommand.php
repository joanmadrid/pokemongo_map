<?php
namespace PokemonBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use PokemonBundle\Entity\Locality;
use PokemonBundle\Entity\PokemonLocation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateLocalitiesCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('pokemon:calculate:localities')
            ->setDescription('Calculate localities');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inserts = array();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('doctrine')->getRepository('PokemonBundle:PokemonLocation');
        $lr = $this->getContainer()->get('doctrine')->getRepository('PokemonBundle:Locality');
        /** @var Query $query */
        $query = $repository->createQueryBuilder('pl')
            ->where('pl.calculated = :calculated')
            ->setParameter('calculated', false)
            ->getQuery();

        $pokemonLocations = $query->getResult();

        $temp = 0;

        /** @var PokemonLocation $pokemonLocation */
        foreach ($pokemonLocations as $pokemonLocation) {
            $apiResult = $this->getAPI($pokemonLocation->getLat(), $pokemonLocation->getLon(), $output);

            // Check if exists error on GOOGLE API
            if (!$apiResult) {
                sleep(2);
                $output->writeln('Sleep 2...');

                continue;
            }

            if (array_key_exists(0, $apiResult) && array_key_exists('address_components', $apiResult[0]) && array_key_exists(2, $apiResult[0]['address_components'])) {
                $neighborhood = $apiResult[0]['address_components'][2]['long_name'];

                if ($neighborhood == "") {
                    continue;
                }

                if (!array_key_exists($neighborhood, $inserts)) {
                    $location = $this->addValue($em, $lr, $neighborhood, 0, $pokemonLocation->getLat(), $pokemonLocation->getLon());
                    $inserts[$location->getName()] = $location;
                } else {
                    $location = $inserts[$neighborhood];
                    $location->add(1);
                }

                $em->persist($location);

                $pokemonLocation->setCalculated(true);
                $em->persist($pokemonLocation);

                $temp += 1;
                if ($temp >= 100) {
                    $output->writeln('Insertando bloque de 100');

                    $temp = 0;
                    $em->flush();

                    if (!$this->continueScanning($lr, $output)) {
                        break;
                    }
                }
            }
        }
    }

    private function getAPI($lat, $lon, $output)
    {
        try {
            $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $lat . "," . $lon . "&key=AIzaSyBWL1YJElYUMHnzPvxpxFCfN-ohfuMHO4s";
            $resp_json = file_get_contents($url);
            $resp = json_decode($resp_json, true);
        } catch (Exception $e) {
            $output->writeln('Crash... ' + $e->getMessage());
            return false;
        }

        if ($resp['status'] == 'OK') {
            return $resp['results'];
        } else {
            var_dump($resp);
            return false;
        }
    }

    private function continueScanning($lr, $out)
    {
        $lq = $lr->createQueryBuilder('l')
            ->select('SUM(l.count)');

        $count = $lq->getQuery()->getSingleScalarResult();
        $out->writeln('Pokemons found: ' + $count);

        if ($count >= 70000) {
            return false;
        } else {
            return true;
        }

    }

    private function addValue($em, $lr, $name, $level, $lat, $lon)
    {
        $result = $lr->createQueryBuilder('l')
            ->where('l.name = :name')
            ->andWhere('l.level = :level')
            ->setParameters(array('name' => $name, 'level' => $level))
            ->getQuery()
            ->getOneOrNullResult();

        if ($result) {
            $locality = $result;
            $locality->add(1);
        } else {
            $locality = new Locality();
            $locality->setName($name);
            $locality->setLevel($level);
            $locality->setCount(1);
            $locality->setLat($lat);
            $locality->setLon($lon);
        }
        return $locality;
    }
}