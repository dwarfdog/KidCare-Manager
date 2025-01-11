<?php

namespace App\Controller;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\MonthlyPayment;
use App\Repository\CareRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MonthlyPaymentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/care', name: 'app_care_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CareController extends AbstractController
{
    #[Route('/planning/{id?}', name: 'index')]
    #[IsGranted('ROLE_USER')]
    public function index(
        CareRepository $careRepository,
        ?Nanny $nanny = null
    ): Response
    {
        $user = $this->getUser();
        $nannies = $user->getNannies();

        // Si une nounou est passée et qu'elle n'appartient pas à l'utilisateur
        if ($nanny && !$nanny->getUsers()->contains($user)) {
            $this->addFlash('warning', 'Vous n\'avez pas accès à cette nounou.');
            return $this->redirectToRoute('app_care_index');
        }

        // Récupération des gardes si une nounou est sélectionnée
        $events = [];
        if ($nanny) {
            $cares = $careRepository->findBy(['user' => $user, 'nanny' => $nanny]);

            foreach ($cares as $care) {
                $events[] = [
                    'id' => $care->getId(),
                    'title' => $care->getMealsCount() . ' repas',
                    'start' => $care->getDate()->format('Y-m-d') . 'T' . $care->getStartTime()->format('H:i:s'),
                    'end' => $care->getDate()->format('Y-m-d') . 'T' . $care->getEndTime()->format('H:i:s'),
                ];
            }
        }

        return $this->render('care/index.html.twig', [
            'nannies' => $nannies,
            'selected_nanny' => $nanny,
            'events' => $events
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        MonthlyPaymentRepository $monthlyPaymentRepository,
        CareRepository $careRepository
    ): JsonResponse {
        // Récupérer les paramètres de la requête GET
        $nannyId = (int)$request->query->get('nanny');
        $startRaw = $request->query->get('start');
        $endRaw = $request->query->get('end');
        $meals = (int)$request->query->getInt('meals');
        $user = $this->getUser();
        try {
            // Conversion en objets DateTime
            $startDateTime = new \DateTime($startRaw);
            $endDateTime = new \DateTime($endRaw);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Les dates ne sont pas bonnes'], 400);
        }

        $nanny = $em->getRepository(Nanny::class)->find($nannyId);

        if (!$nanny || !$nanny->getUsers()->contains($user)) {
            return new JsonResponse(['error' => 'La Nounou n\'est pas valide.'], 400);
        }

        $conflictingCare = $careRepository->findConflictingCare(
            $nannyId,
            $startDateTime->format('Y-m-d'), // Date au format "YYYY-MM-DD"
            $startDateTime->format('H:i:s'), // Heure au format "HH:MM:SS"
            $endDateTime->format('H:i:s')    // Heure au format "HH:MM:SS"
        );
        if ($conflictingCare) {
            return new JsonResponse(['error' => 'Un garde existe déjà pour cette nounou à cette date et dans cet intervalle de temps.'], 400);
        }

        try {
            $em->beginTransaction();
            try {
                // Aucun conflit, création d'une nouvelle garde
                $newCare = new Care();
                $newCare->setUser($user);
                $newCare->setNanny($nanny);
                $newCare->setDate($startDateTime);
                $newCare->setStartTime($startDateTime);
                $newCare->setEndTime($endDateTime);
                $newCare->setMealsCount($meals);
                $newCare->setCreatedAt(new \DateTime());

                // Calcul de la différence entre les deux heures
                $interval = $startDateTime->diff($endDateTime);
                // Convertir la différence en heures décimales
                $hoursCount = $interval->h + ($interval->i / 60); // Heures + minutes en fraction d'heure

                // Affecter le nombre d'heures à la garde
                $newCare->setHoursCount($hoursCount);

                // Persister la nouvelle garde
                $em->persist($newCare);
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la création de la garde.'], 500);
            }

            try {
                $month = clone $startDateTime;
                $month->modify('first day of this month');
                $month->setTime(0, 0, 0);
                $monthlyPayment = $monthlyPaymentRepository->findOneBy([
                    'user' => $user,
                    'nanny' => $nanny,
                    'month' => $month
                ]) ?? new MonthlyPayment();
                if ($monthlyPayment->getId() === null) {
                    $monthlyPayment->setUser($user)
                        ->setNanny($nanny)
                        ->setMonth($month)
                        ->setCreatedAt(new \DateTime());
                }
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la récupération du paiement mensuel.'], 500);
            }

            try {
                // Mise à jour des totaux
                $monthlyPayment->setTotalsHours($monthlyPayment->getTotalsHours() + $hoursCount);
                $monthlyPayment->setTotalMeals($monthlyPayment->getTotalMeals() + $meals);
                // Calcul des montants
                $amountHours = $hoursCount * $nanny->getHourlyRate();
                $amountMeals = $meals * $nanny->getMealRate();
                $monthlyPayment->setAmountHours($monthlyPayment->getAmountHours() + $amountHours);
                $monthlyPayment->setAmountMeals($monthlyPayment->getAmountMeals() + $amountMeals);
                $monthlyPayment->setTotalAmount($monthlyPayment->getTotalAmount() + $amountHours + $amountMeals);
                // Persister le paiement mensuel
                $em->persist($monthlyPayment);
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour du paiement mensuel.'], 500);
            }

            try {
                $em->flush();
                $em->commit();
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la sauvegarde du paiement mensuel.'], 500);
            }

        } catch (\Throwable $th) {
            $em->rollback();
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la transaction.'], 500);
        }

        return $this->json([
            'id' => $newCare->getId(),
            'monthlyPayment' => [
                'id' => $monthlyPayment->getId(),
                'totalAmount' => $monthlyPayment->getTotalAmount()
            ]
        ]);
    }
}
