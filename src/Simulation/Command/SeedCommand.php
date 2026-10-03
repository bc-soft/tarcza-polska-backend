<?php

declare(strict_types=1);

namespace App\Simulation\Command;

use App\Identity\Entity\Operator;
use App\Identity\Repository\OperatorRepository;
use App\Shared\Geo\Point;
use App\Shelter\Entity\Shelter;
use App\Shelter\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'tarcza:seed', description: 'Create demo operator accounts and a starter set of shelters (Poznań)')]
final class SeedCommand extends Command
{
    /** @var list<array{string, float, float, int, string}> name, lat, lng, capacity, address */
    private const array SHELTERS = [
        ['Schron - Stary Browar (parking P2)', 52.4027, 16.9245, 800, 'ul. Półwiejska 42'],
        ['Schron - Dworzec Główny (poziom -2)', 52.4019, 16.9114, 1200, 'ul. Dworcowa 2'],
        ['Schron - Politechnika Poznańska (CW)', 52.4030, 16.9500, 600, 'ul. Piotrowo 3'],
        ['Schron - Osiedle Jana III Sobieskiego', 52.4612, 16.9208, 400, 'os. Jana III Sobieskiego 1'],
        ['Schron - Hala Arena', 52.3978, 16.8826, 2000, 'ul. Wyspiańskiego 33'],
        ['Schron - UAM Morasko (parking)', 52.4664, 16.9268, 500, 'ul. Uniwersytetu Poznańskiego 4'],
        ['Schron - Jeżyce, Rynek Jeżycki', 52.4121, 16.9012, 300, 'Rynek Jeżycki'],
        ['Schron - Rataje, Galeria Posnania', 52.3942, 16.9640, 1500, 'ul. Pleszewska 1'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OperatorRepository $operators,
        private readonly ShelterRepository $shelters,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the demo accounts', 'tarcza-demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $password */
        $password = $input->getOption('password');

        foreach ([
            ['operator@tarcza.local', 'Operator dyżurny', ['ROLE_OPERATOR']],
            ['analyst@tarcza.local', 'Analityk', ['ROLE_ANALYST']],
            ['admin@tarcza.local', 'Administrator', ['ROLE_ADMIN']],
        ] as [$email, $name, $roles]) {
            if (null !== $this->operators->findOneBy(['email' => $email])) {
                $io->text("operator $email already exists");
                continue;
            }
            $operator = new Operator($email, $name, $roles);
            $operator->setPassword($this->hasher->hashPassword($operator, $password));
            $operator->setOrganisation('Centrum Zarządzania Kryzysowego - demo');
            $this->em->persist($operator);
            $io->text("created operator $email");
        }

        $existing = \count($this->shelters->findAll());
        if (0 === $existing) {
            foreach (self::SHELTERS as [$name, $lat, $lng, $capacity, $address]) {
                $shelter = new Shelter($name, new Point($lat, $lng), 'seed');
                $shelter->setCapacity($capacity);
                $shelter->setAddress($address);
                $this->em->persist($shelter);
            }
            $io->text(\sprintf('created %d shelters', \count(self::SHELTERS)));
        } else {
            $io->text("$existing shelters already present, skipping");
        }

        $this->em->flush();
        $io->success(\sprintf('Seed done. Command Center login: operator@tarcza.local / %s', $password));

        return Command::SUCCESS;
    }
}
