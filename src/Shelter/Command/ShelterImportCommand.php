<?php

declare(strict_types=1);

namespace App\Shelter\Command;

use App\Shared\Geo\BoundingBox;
use App\Shared\Geo\Point;
use App\Shelter\Entity\Shelter;
use App\Shelter\Enum\ShelterAvailability;
use App\Shelter\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports "Punkty schronienia w Polsce" (dane.gov.pl dataset 28058, ~86 500 rows, CSV with BOM) into Shelter.
 * Upserts by the public identifier; citizen confirmations (status, occupancy) are never overwritten.
 *
 *   bin/console tarcza:shelters:import --wojewodztwo=wielkopolskie
 *   bin/console tarcza:shelters:import --around=52.4121,16.9012 --radius-km=20
 *   bin/console tarcza:shelters:import --bbox=16.70,52.30,17.15,52.55 --limit=500
 *   bin/console tarcza:shelters:import --file=/path/punkty_schronienia.csv   (offline copy)
 */
#[AsCommand(name: 'tarcza:shelters:import', description: 'Import shelters from the national register on dane.gov.pl (CSV), optionally filtered by region / area')]
final class ShelterImportCommand extends Command
{
    public const string SOURCE = 'dane.gov.pl';
    public const string DEFAULT_URL = 'https://gdziesieukryc.pl/PS_XML/punkty_schronienia.csv';

    private const array COLUMNS = [
        'id' => 'Identyfikator publiczny',
        'name' => 'Nazwa',
        'kind' => 'Rodzaj obiektu',
        'description' => 'Opis ogolny',
        'gmina' => 'Gmina',
        'powiat' => 'Powiat',
        'voivodeship' => 'Wojewodztwo',
        'lat' => 'Szerokosc geograficzna',
        'lng' => 'Dlugosc geograficzna',
        'address' => 'Adres',
        'availability' => 'Dostepnosc',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ShelterRepository $shelters,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'CSV resource URL', self::DEFAULT_URL)
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Local CSV file instead of downloading')
            ->addOption('wojewodztwo', null, InputOption::VALUE_REQUIRED, 'Only this voivodeship (e.g. wielkopolskie)')
            ->addOption('around', null, InputOption::VALUE_REQUIRED, 'Center "lat,lng" (with --radius-km)')
            ->addOption('radius-km', null, InputOption::VALUE_REQUIRED, 'Radius around the center', '20')
            ->addOption('bbox', null, InputOption::VALUE_REQUIRED, 'minLng,minLat,maxLng,maxLat')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after N imported rows (0 = all)', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count only, store nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = $this->resolveFile($input, $io);
        if (null === $path) {
            return Command::FAILURE;
        }

        /** @var string|null $voivodeship */
        $voivodeship = $input->getOption('wojewodztwo');
        $voivodeship = null === $voivodeship ? null : mb_strtolower(trim($voivodeship));
        $bbox = $this->resolveBbox($input);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        $handle = fopen($path, 'r');
        if (false === $handle) {
            $io->error('Cannot open '.$path);

            return Command::FAILURE;
        }
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ("\xEF\xBB\xBF" !== $bom) {
            rewind($handle);
        }
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!\is_array($header)) {
            $io->error('Empty CSV');

            return Command::FAILURE;
        }
        $index = array_flip(array_map('strval', $header));
        foreach (self::COLUMNS as $column) {
            if (!isset($index[$column])) {
                $io->error(\sprintf('Column "%s" not found; got: %s', $column, implode(' | ', $header)));

                return Command::FAILURE;
            }
        }

        $scanned = 0;
        $matched = 0;
        $created = 0;
        $updated = 0;
        $progress = $io->createProgressBar();
        $progress->setFormat(' %current% rows scanned, %message%');
        $progress->setMessage('0 matched');

        while (false !== ($row = fgetcsv($handle, 0, ',', '"', '\\'))) {
            ++$scanned;
            if (0 === $scanned % 2000) {
                $progress->setMessage(\sprintf('%d matched', $matched));
                $progress->setProgress($scanned);
            }
            $get = static fn (string $key): string => trim((string) ($row[$index[self::COLUMNS[$key]]] ?? ''));

            if (null !== $voivodeship && mb_strtolower($get('voivodeship')) !== $voivodeship) {
                continue;
            }
            $lat = (float) $get('lat');
            $lng = (float) $get('lng');
            if ($lat < 49.0 || $lat > 55.0 || $lng < 14.0 || $lng > 24.5) {
                continue;
            }
            if (null !== $bbox && ($lat < $bbox->minLat || $lat > $bbox->maxLat || $lng < $bbox->minLng || $lng > $bbox->maxLng)) {
                continue;
            }
            $externalId = $get('id');
            if ('' === $externalId) {
                continue;
            }
            ++$matched;

            if (!$dryRun) {
                $address = $get('address');
                $name = self::displayName($get('name'), $address, $get('gmina'));
                $existing = $this->shelters->findByExternalId(self::SOURCE, $externalId);
                if (null === $existing) {
                    $shelter = new Shelter($name, new Point($lat, $lng), self::SOURCE);
                    $shelter->setExternalId($externalId);
                    $this->em->persist($shelter);
                    ++$created;
                } else {
                    $shelter = $existing;
                    $shelter->rename($name);
                    $shelter->relocate(new Point($lat, $lng));
                    ++$updated;
                }
                $shelter->setAddress('' !== $address ? $address : null);
                $shelter->setAvailability(ShelterAvailability::fromRegister($get('availability')));
                $shelter->setRegion(array_values(array_filter([$get('gmina'), $get('powiat'), $get('voivodeship')])));

                if (0 === $matched % 500) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            if ($limit > 0 && $matched >= $limit) {
                break;
            }
        }
        fclose($handle);
        $progress->finish();
        $io->newLine(2);

        if ($dryRun) {
            $io->success(\sprintf('Dry run: %d rows scanned, %d would be imported.', $scanned, $matched));

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(\sprintf('Shelters: %d scanned, %d matched, %d created, %d updated (source %s).', $scanned, $matched, $created, $updated, self::SOURCE));

        return Command::SUCCESS;
    }

    private function resolveFile(InputInterface $input, SymfonyStyle $io): ?string
    {
        /** @var string|null $file */
        $file = $input->getOption('file');
        if (null !== $file) {
            return is_file($file) ? $file : null;
        }
        /** @var string $url */
        $url = $input->getOption('url');
        $io->text('Downloading '.$url);
        $tmp = tempnam(sys_get_temp_dir(), 'shelters-');
        if (false === $tmp) {
            return null;
        }
        $out = fopen($tmp, 'w');
        if (false === $out) {
            return null;
        }
        $response = $this->httpClient->request('GET', $url, ['timeout' => 120]);
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($out, $chunk->getContent());
        }
        fclose($out);
        $io->text(\sprintf('Downloaded %.1f MB', filesize($tmp) / 1048576));

        return $tmp;
    }

    private function resolveBbox(InputInterface $input): ?BoundingBox
    {
        /** @var string|null $bbox */
        $bbox = $input->getOption('bbox');
        if (null !== $bbox) {
            return BoundingBox::fromString($bbox);
        }
        /** @var string|null $around */
        $around = $input->getOption('around');
        if (null === $around) {
            return null;
        }
        [$lat, $lng] = array_map('floatval', explode(',', $around) + [0 => '0', 1 => '0']);

        return BoundingBox::around(new Point($lat, $lng), (int) round((float) $input->getOption('radius-km') * 1000));
    }

    /** The register calls every row "Miejsce ochronne"; the address is what tells them apart. */
    public static function displayName(string $name, string $address, string $gmina): string
    {
        $base = '' !== $name ? $name : 'Miejsce ochronne';
        if ('' !== $address) {
            return mb_substr($base.' · '.$address, 0, 160);
        }

        return mb_substr('' !== $gmina ? $base.' · '.$gmina : $base, 0, 160);
    }
}
