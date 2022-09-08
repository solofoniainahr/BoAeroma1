<?php

namespace App\Twig;

use App\Repository\PositionGammeClientRepository;
use App\Service\TrieProduit;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TrieExtension extends AbstractExtension
{

    private $trieProduit;
    private $positionGammeClientRepo;
    

    public function __construct(TrieProduit $trieProduit, PositionGammeClientRepository $positionGammeClientRepo)
    {
        $this->trieProduit = $trieProduit;
        $this->positionGammeClientRepo = $positionGammeClientRepo;
        $this->positionGammeClientRepo = $positionGammeClientRepo;

    }

    public function getFilters()
    {
        return [
            new TwigFilter('trier', [$this, 'trier']),
        ];
    }

    public function trier($commande, $client)
    {
    
        $gammesClient = $this->positionGammeClientRepo->findBy(['client' => $client ], ['position' => 'asc']);

        $commandeTrier = $this->trieProduit->setGammesClient($gammesClient)
                                    ->setCommandes($commande)
                                    ->trieCommande();

        return $commandeTrier;
    }
}