<?php
namespace PokemonBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Repository\RepositoryFactory;
use PokemonBundle\Entity\Locality;
use PokemonBundle\Entity\PokemonLocation;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

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
            $apiResult = $this->getAPI($pokemonLocation->getLat(), $pokemonLocation->getLon());

            // Check if exists error on GOOGLE API
            if (!$apiResult) {
                sleep(2);
                continue;
            }

            $neighborhood = $apiResult[0]['address_components'][2]['long_name'];
            $city = $apiResult[0]['address_components'][3]['long_name'];

            if ($neighborhood == "" || $city == "") {
                continue;
            }

            $loc1 = $this->addValue($em, $lr, $neighborhood, 0, $pokemonLocation->getLat(), $pokemonLocation->getLon());
            $em->persist($loc1);

            $loc2 = $this->addValue($em, $lr, $city, 1, $pokemonLocation->getLat(), $pokemonLocation->getLon());
            $em->persist($loc2);

            $output->writeln('Calculated location level 1: ' . $neighborhood);
            $output->writeln('Calculated location level 2: ' . $city);

            $pokemonLocation->setCalculated(true);
            $em->persist($pokemonLocation);

            $temp += 1;

            if ($temp >= 100) {
                $temp = 0;
                $em->flush();
            }
        }


    }

    private function getAPI($lat, $lon)
    {
        $url = "http://maps.googleapis.com/maps/api/geocode/json?latlng=" . $lat . "," . $lon;
        $resp_json = file_get_contents($url);
        $resp = json_decode($resp_json, true);

        if ($resp['status'] == 'OK') {
            return $resp['results'];
        } else {
            return false;
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