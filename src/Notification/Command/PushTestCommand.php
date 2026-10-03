<?php

declare(strict_types=1);

namespace App\Notification\Command;

use App\Identity\Repository\DeviceRepository;
use App\Notification\FcmPushSender;
use App\Notification\PushMessage;
use App\Notification\PushSenderInterface;
use Kreait\Firebase\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Checks the Firebase credentials and optionally sends a test push to one FCM token or one registered device.
 *
 *   bin/console tarcza:push:test                       # credentials + project check only
 *   bin/console tarcza:push:test <fcm-token>           # send a test notification to that token
 *   bin/console tarcza:push:test --device=<device-id>  # send to a device registered through the API
 */
#[AsCommand(name: 'tarcza:push:test', description: 'Verify Firebase credentials and send a test push to a token or a registered device')]
final class PushTestCommand extends Command
{
    public function __construct(
        private readonly PushSenderInterface $pushSender,
        private readonly DeviceRepository $devices,
        private readonly string $firebaseCredentials,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::OPTIONAL, 'FCM registration token to send a test push to')
            ->addOption('device', null, InputOption::VALUE_REQUIRED, 'Device UUID (uses its stored push token)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Notification title', 'Tarcza Polska - test')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'Notification body', 'Jeśli to widzisz, push z backendu działa.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === $this->firebaseCredentials || !is_file($this->firebaseCredentials)) {
            $io->error(\sprintf('FIREBASE_CREDENTIALS is empty or the file does not exist (%s). Set it in .env.local.', $this->firebaseCredentials));

            return Command::FAILURE;
        }

        /** @var array{project_id?: string, client_email?: string} $account */
        $account = json_decode((string) file_get_contents($this->firebaseCredentials), true, 512, \JSON_THROW_ON_ERROR);
        $io->text([
            'credentials: '.$this->firebaseCredentials,
            'project:     '.($account['project_id'] ?? '?'),
            'account:     '.($account['client_email'] ?? '?'),
            'sender:      '.$this->pushSender::class,
        ]);

        if (!$this->pushSender instanceof FcmPushSender) {
            $io->warning('The container still uses the logging sender. Restart php/worker after changing FIREBASE_CREDENTIALS.');
        }

        // Round-trip with Firebase (OAuth token exchange + API call). A bogus token is reported as invalid,
        // an authentication problem throws.
        try {
            $messaging = new Factory()->withServiceAccount($this->firebaseCredentials)->createMessaging();
            $validation = $messaging->validateRegistrationTokens(['tarcza-credentials-check']);
            $io->success(\sprintf('Firebase accepted the credentials (probe token classified as: %s).', implode(',', array_keys(array_filter($validation)))));
        } catch (Throwable $e) {
            $io->error('Firebase rejected the credentials: '.$e->getMessage());

            return Command::FAILURE;
        }

        /** @var string|null $token */
        $token = $input->getArgument('token');
        /** @var string|null $deviceId */
        $deviceId = $input->getOption('device');
        if ((null === $token || '' === $token) && null === $deviceId) {
            $io->note('Pass an FCM token or --device=<uuid> to actually send a test push.');

            return Command::SUCCESS;
        }

        /** @var string $title */
        $title = $input->getOption('title');
        /** @var string $body */
        $body = $input->getOption('body');
        $message = new PushMessage($title, $body, ['type' => 'test']);

        if (null !== $deviceId) {
            $device = $this->devices->find(Uuid::fromString($deviceId));
            if (null === $device) {
                $io->error('Device not found.');

                return Command::FAILURE;
            }
            if (!$device->canReceivePush()) {
                $io->error('Device has no push token or is simulated.');

                return Command::FAILURE;
            }
            $sent = $this->pushSender->send([$device], $message);
            $io->success(\sprintf('%d push handed to FCM for device %s.', $sent, $deviceId));

            return Command::SUCCESS;
        }

        if (null === $token || '' === $token) {
            return Command::SUCCESS;
        }

        try {
            $messaging->send(\Kreait\Firebase\Messaging\CloudMessage::new()
                ->toToken($token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData(['type' => 'test']));
            $io->success('Test push sent to the given token.');
        } catch (Throwable $e) {
            $io->error('Send failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
