<?php

namespace App\Controller;

use App\Entity\BankAccount;
use App\Entity\BankCategory;
use App\Repository\BankAccountRepository;
use App\Repository\BankCategoryRepository;
use App\Repository\BankAnalyticsFavoriteRepository;
use App\Service\BankAccountManager;
use App\Service\BankCategoryAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bank/analytics', name: 'app_bank_analytics')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class BankAnalyticsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(
        Request $request,
        BankAccountRepository $accountRepository,
        BankCategoryRepository $categoryRepository,
        BankCategoryAnalyticsService $analytics,
        BankAccountManager $accountManager,
        BankAnalyticsFavoriteRepository $favorites,
    ): Response {
        $context = $this->context($request, $accountRepository, $categoryRepository, $analytics);

        return $this->render('bank/analytics.html.twig', $context + ['accountManager' => $accountManager, 'favorites' => $favorites->findForUser($this->getUser())]);
    }

    #[Route('/data', name: '_data', methods: ['GET'])]
    public function data(
        Request $request,
        BankAccountRepository $accountRepository,
        BankCategoryRepository $categoryRepository,
        BankCategoryAnalyticsService $analytics,
    ): JsonResponse {
        $context = $this->context($request, $accountRepository, $categoryRepository, $analytics);

        return $this->json([
            'data' => $context['analyticsData'],
            'error' => $context['analyticsError'],
            'category' => $context['selectedCategory']?->getName(),
            'from' => $context['from']->format('Y-m-d'),
            'to' => $context['to']->format('Y-m-d'),
        ]);
    }

    /** @return array<string, mixed> */
    private function context(
        Request $request,
        BankAccountRepository $accountRepository,
        BankCategoryRepository $categoryRepository,
        BankCategoryAnalyticsService $analytics,
    ): array {
        $user = $this->getUser();
        $accounts = $accountRepository->findActiveForUser($user);
        $categories = array_values(array_filter($categoryRepository->findActiveForUser($user), fn(BankCategory $category): bool => $analytics->hasAnyData($user, $category, $accounts)));
        $selectedCategory = $this->selectedCategory($request, $categories);
        $selectedAccounts = $this->selectedAccounts($request, $accounts);
        [$period, $from, $to, $error] = $this->period($request);

        $data = ['labels' => [], 'gains' => [], 'expenses' => [], 'balance' => [], 'rows' => [], 'hasData' => false];
        if ($selectedCategory === null) {
            $error ??= 'Aucune categorie active ne contient de donnees exploitables.';
        } elseif ($selectedAccounts === []) {
            $error ??= 'Selectionner au moins un compte actif.';
        } elseif ($error === null) {
            $data = $analytics->evolution($user, $selectedCategory, $selectedAccounts, $from, $to);
        }

        return [
            'accounts' => $accounts,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'selectedAccounts' => $selectedAccounts,
            'selectedAccountIds' => array_map(static fn(BankAccount $account): string => $account->getPublicId(), $selectedAccounts),
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'analyticsData' => $data,
            'analyticsError' => $error,
        ];
    }

    /** @param BankCategory[] $categories */
    private function selectedCategory(Request $request, array $categories): ?BankCategory
    {
        $requested = trim((string) $request->query->get('category', ''));
        foreach ($categories as $category) {
            if ($requested === '' || $category->getPublicId() === $requested) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @param BankAccount[] $accounts
     * @return BankAccount[]
     */
    private function selectedAccounts(Request $request, array $accounts): array
    {
        $query = $request->query->all();
        if (!array_key_exists('accounts', $query)) {
            return $accounts;
        }

        $requested = is_array($query['accounts']) ? $query['accounts'] : [$query['accounts']];
        $requested = array_map('strval', $requested);

        return array_values(array_filter(
            $accounts,
            static fn(BankAccount $account): bool => in_array($account->getPublicId(), $requested, true),
        ));
    }

    /** @return array{string, \DateTimeImmutable, \DateTimeImmutable, ?string} */
    private function period(Request $request): array
    {
        $today = new \DateTimeImmutable('today');
        $period = (string) $request->query->get('period', '6');
        if ($period === '12') {
            return [$period, $today->modify('first day of this month')->modify('-11 months'), $today, null];
        }
        if ($period !== 'custom') {
            return ['6', $today->modify('first day of this month')->modify('-5 months'), $today, null];
        }

        $from = $this->date($request, 'from');
        $to = $this->date($request, 'to');
        if ($from === null || $to === null || $from > $to) {
            return ['custom', $from ?? $today->modify('first day of this month')->modify('-5 months'), $to ?? $today, 'La periode personnalisee est invalide.'];
        }

        return ['custom', $from, $to, null];
    }

    private function date(Request $request, string $name): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get($name, ''));
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date : null;
    }
}
