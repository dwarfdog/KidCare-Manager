<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly bool $registrationEmailVerificationRequired,
        private readonly string $mailerDsn,
        private readonly string $mailerFrom,
        private readonly int $graceDays,
    ) {
    }

    public function isRequired(): bool
    {
        return $this->registrationEmailVerificationRequired;
    }

    public function assertConfigured(): void
    {
        if ($this->isRequired() && str_starts_with($this->mailerDsn, 'null://')) {
            throw new \DomainException('La validation email est activee mais MAILER_DSN utilise null://null.');
        }

        if ($this->isRequired() && $this->graceDays > 15) {
            throw new \DomainException('La validation email est activee mais EMAIL_VERIFICATION_GRACE_DAYS depasse 15 jours.');
        }
    }

    public function prepareVerification(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationTokenHash(hash('sha256', $token));
        $user->setEmailVerificationRequestedAt(new \DateTime());

        return $token;
    }

    public function sendVerificationEmail(User $user, string $token): void
    {
        if (!$this->isRequired() || $user->getEmail() === null) {
            return;
        }

        $url = $this->urlGenerator->generate('app_verify_email', [
            'publicId' => $user->getPublicId(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Validation de votre compte KidCare Manager')
            ->htmlTemplate('registration/verify_email.html.twig')
            ->context(['verificationUrl' => $url, 'user' => $user]);

        $this->mailer->send($email);
    }

    public function verify(User $user, string $token): bool
    {
        $expected = $user->getEmailVerificationTokenHash();
        if ($expected === null || !hash_equals($expected, hash('sha256', $token))) {
            return false;
        }

        $user->setEmailVerified(true);
        $user->setEmailVerificationRequestedAt(null);

        return true;
    }

    public function graceExpired(User $user, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isRequired() || $user->isEmailVerified()) {
            return false;
        }

        $now ??= new \DateTimeImmutable();
        $reference = $user->getEmailVerificationRequestedAt() ?? $user->getCreatedAt();
        $deadline = \DateTimeImmutable::createFromInterface($reference)->modify('+' . $this->graceDays . ' days');

        return $now > $deadline;
    }

    public function getGraceDays(): int
    {
        return $this->graceDays;
    }
}
