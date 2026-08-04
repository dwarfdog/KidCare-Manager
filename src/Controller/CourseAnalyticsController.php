<?php

namespace App\Controller;

use App\Entity\CourseProduct;
use App\Repository\CourseProductRepository;
use App\Service\Course\CourseProductManager;
use App\Service\Course\CourseProductPriceAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Evolution du prix dans le temps et comparatif entre magasins pour un produit. Meme decoupage
 * qu'App\Controller\BankAnalyticsController : une page HTML (selecteur de produit + graphique), les
 * donnees etant embarquees directement dans le HTML (assets/controllers/course_price_chart_controller.js).
 */
#[Route('/courses/analytics', name: 'app_courses_analytics')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class CourseAnalyticsController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request, CourseProductRepository $productRepository, CourseProductManager $productManager, CourseProductPriceAnalyticsService $analytics): Response
    {
        $context = $this->context($request, $productRepository, $productManager, $analytics);

        return $this->render('courses/analytics.html.twig', $context);
    }

    /** @return array<string, mixed> */
    private function context(Request $request, CourseProductRepository $productRepository, CourseProductManager $productManager, CourseProductPriceAnalyticsService $analytics): array
    {
        $user = $this->getUser();
        $products = $productRepository->findActiveForUser($user);
        $selected = $this->selectedProduct($request, $products);

        return [
            'products' => $products,
            'productManager' => $productManager,
            'selectedProduct' => $selected,
            'evolution' => $selected !== null ? $analytics->evolution($user, $selected) : null,
            'comparison' => $selected !== null ? $analytics->latestPricesByStore($user, $selected) : [],
        ];
    }

    /** @param CourseProduct[] $products */
    private function selectedProduct(Request $request, array $products): ?CourseProduct
    {
        $requested = trim((string) $request->query->get('product', ''));
        foreach ($products as $product) {
            if ($requested === '' || $product->getPublicId() === $requested) {
                return $requested === '' ? null : $product;
            }
        }

        return null;
    }
}
