<?php

namespace App\Controller;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\MonthlyPayment;
use App\Service\RoundingService;
use App\Repository\CareRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CareTemplateRepository;
use App\Repository\MonthlyPaymentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/care', name: 'app_care_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CareController extends AbstractController
{

    private RoundingService $roundingService;

    public function __construct(
        RoundingService $roundingService
    )
    {
        $this->roundingService = $roundingService;
    }

    #[Route('/planning/{slug?}/{currentDate?}', name: 'index')]
    #[IsGranted('ROLE_USER')]
    public function index(
        CareRepository $careRepository,
        CareTemplateRepository $careTemplateRepository,
        ?Nanny $nanny = null,
        ?string $currentDate = null
    ): Response {
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

            $events = [];
            foreach ($cares as $care) {
                if (!$care->getDate() || !$care->getStartTime() || !$care->getEndTime()) {
                    continue;
                }
                if ($care->getMealsCount() === 0) {
                    $events[] = [
                        'id' => $care->getId(),
                        'title' => $care->getHoursCount() . ' heures', // Titre avec le nombre d'heures
                        'start' => $care->getDate()->format('Y-m-d') . 'T' . $care->getStartTime()->format('H:i:s'),
                        'end' => $care->getDate()->format('Y-m-d') . 'T' . $care->getEndTime()->format('H:i:s'),
                        'meals' => $care->getMealsCount(),
                        'description' => sprintf(
                            'De %s à %s',
                            $care->getStartTime()->format('H:i'),
                            $care->getEndTime()->format('H:i')
                        ),
                    ];
                } else {
                    $events[] = [
                        'id' => $care->getId(),
                        'title' => $care->getHoursCount() . ' heures', // Titre avec le nombre d'heures
                        'start' => $care->getDate()->format('Y-m-d') . 'T' . $care->getStartTime()->format('H:i:s'),
                        'end' => $care->getDate()->format('Y-m-d') . 'T' . $care->getEndTime()->format('H:i:s'),
                        'meals' => $care->getMealsCount(),
                        'description' => sprintf(
                            'De %s à %s<br>%d repas',
                            $care->getStartTime()->format('H:i'),
                            $care->getEndTime()->format('H:i'),
                            $care->getMealsCount()
                        ),
                    ];
                }
            }
        }

        if ($nanny === null) {
            $templates = false;
        }
        else {
            $templates = $careTemplateRepository->findByUserAndNanny($user, $nanny) ?? false;
        }

        if ($this->isValidDate($currentDate)) {
            $date = new \DateTime($currentDate);
        } else {
            $date = new \DateTime();
        }

        return $this->render('care/index.html.twig', [
            'nannies' => $nannies,
            'selected_nanny' => $nanny,
            'currentDate' => $date->format('Y-m-d'),
            'events' => $events,
            'templates' => $templates,
            'show_sidebar' => true,
        ]);
    }

    /**
     * Vérifie si une chaîne donnée est une date valide.
     *
     * @param ?string $currentDate La chaîne représentant la date ou null.
     * @return bool True si la date est valide, false sinon.
     */
    function isValidDate(?string $currentDate): bool
    {
        if ($currentDate === null || trim($currentDate) === '') {
            return false; // La date est nulle ou vide.
        }

        try {
            $date = new \DateTime($currentDate);
            // Optionnel : Vérifiez le format exact si nécessaire.
            return $date->format('Y-m-d') === $currentDate;
        } catch (Exception $e) {
            return false; // Une exception signifie que la date est invalide.
        }
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
            $nanny->getId(),
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
                $newCare->setMonth($startDateTime->format('Y-m'));
                $newCare->setDate($startDateTime);
                $newCare->setStartTime($startDateTime);
                $newCare->setEndTime($endDateTime);
                $newCare->setMealsCount($meals);
                $newCare->setCreatedAt(new \DateTime());

                $slugbase = $startDateTime->format('Y-m-d') . '-' . $startDateTime->format('H:i') . '-' . $endDateTime->format('H:i');
                $newCare->setSlugBase($slugbase);

                // Calcul de la différence entre les deux heures
                $interval = $startDateTime->diff($endDateTime);
                // Convertir la différence en heures décimales
                $hoursCount = $interval->h + ($interval->i / 60); // Heures + minutes en fraction d'heure

                // Affecter le nombre d'heures à la garde
                $newCare->setHoursCount($this->roundingService->roundToTwoDecimals($hoursCount));

                // Persister la nouvelle garde
                $em->persist($newCare);
            } catch (\Throwable $th) {
                $em->rollback();
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la création de la garde.'], 500);
            }

            try {
                $month = $startDateTime->format('Y-m');
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
                $em->rollback();
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la récupération du paiement mensuel.'], 500);
            }

            try {
                // Mise à jour des totaux
                $monthlyPayment->setTotalsHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalsHours() + $hoursCount));
                $monthlyPayment->setTotalMeals($monthlyPayment->getTotalMeals() + $meals);
                // Calcul des montants
                $amountHours = $hoursCount * $nanny->getHourlyRate();
                $amountMeals = $meals * $nanny->getMealRate();
                $monthlyPayment->setAmountHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountHours() + $amountHours));
                $monthlyPayment->setAmountMeals($this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountMeals() + $amountMeals));
                $monthlyPayment->setTotalAmount($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalAmount() + $amountHours + $amountMeals));
                // Persister le paiement mensuel
                $em->persist($monthlyPayment);
            } catch (\Throwable $th) {
                $em->rollback();
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour du paiement mensuel.'], 500);
            }

            try {
                $em->flush();
                $em->commit();
            } catch (\Throwable $th) {
                dd($th);
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la sauvegarde du paiement mensuel.'], 500);
            }
        } catch (\Throwable $th) {
            $em->rollback();
            return new JsonResponse(['error' => 'Une erreur est survenue lors de la transaction.'], 500);
        }

        return $this->json([
            'id' => $newCare->getId(),
            'start' => $newCare->getStartTime(),
            'end' => $newCare->getEndTime(),
            'meals' => $newCare->getMealsCount(),
            'monthlyPayment' => [
                'id' => $monthlyPayment->getId(),
                'totalAmount' => $monthlyPayment->getTotalAmount()
            ]
        ]);
    }


    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Care $care,
        EntityManagerInterface $em,
        MonthlyPaymentRepository $monthlyPaymentRepository
    ): JsonResponse {
        $user = $this->getUser();
        if ($care->getUser() !== $user) {
            return new JsonResponse(['error' => 'Vous n\'avez pas accès à cette garde.'], 403);
        }
        $nanny = $care->getNanny();
        $month = $care->getDate()->format('Y-m');
        $monthlyPayment = $monthlyPaymentRepository->findOneBy([
            'user' => $user,
            'nanny' => $nanny,
            'month' => $month
        ]);

        if (!$monthlyPayment) {
            return new JsonResponse(['error' => 'Le paiement mensuel n\'a pas été trouvé.'], 404);
        }


        try {
            $em->beginTransaction();
            try {
                $em->remove($care);
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la suppression de la garde.'], 500);
            }

            try {
                $interval = $care->getStartTime()->diff($care->getEndTime());
                $hoursCount = $interval->h + ($interval->i / 60);
                $amountHours = $hoursCount * $nanny->getHourlyRate();
                $amountMeals = $care->getMealsCount() * $nanny->getMealRate();
                $monthlyPayment->setTotalsHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalsHours() - $hoursCount));
                $monthlyPayment->setTotalMeals($monthlyPayment->getTotalMeals() - $care->getMealsCount());
                $monthlyPayment->setAmountHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountHours() - $amountHours));
                $monthlyPayment->setAmountMeals($this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountMeals() - $amountMeals));
                $monthlyPayment->setTotalAmount($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalAmount() - $amountHours - $amountMeals));
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

        return new JsonResponse(['success' => 'La garde a été supprimée.'], 200);
    }

    #[Route('/update/{id}', name: 'update')]
    public function update(
        Care $care,
        Request $request,
        EntityManagerInterface $em,
        MonthlyPaymentRepository $monthlyPaymentRepository
    ): JsonResponse {
        $user = $this->getUser();
        if ($care->getUser() !== $user) {
            return new JsonResponse(['error' => 'Vous n\'avez pas accès à cette garde.'], 403);
        }

        $nanny = $care->getNanny();
        $month = $care->getDate()->format('Y-m');
        $monthlyPayment = $monthlyPaymentRepository->findOneBy([
            'user' => $user,
            'nanny' => $nanny,
            'month' => $month
        ]);

        if (!$monthlyPayment) {
            return new JsonResponse(['error' => 'Le paiement mensuel n\'a pas été trouvé.'], 404);
        }

        $startRaw = $request->query->get('start');
        $endRaw = $request->query->get('end');
        $meals = (int)$request->query->getInt('meals') ?? null;

        try {
            // Conversion en objets DateTime
            $startDateTime = new \DateTime($startRaw);
            $endDateTime = new \DateTime($endRaw);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Les dates ne sont pas bonnes'], 400);
        }

        $conflictingCare = $em->getRepository(Care::class)->findConflictingCare(
            $nanny->getId(),
            $startDateTime->format('Y-m-d'), // Date au format "YYYY-MM-DD"
            $startDateTime->format('H:i:s'), // Heure au format "HH:MM:SS"
            $endDateTime->format('H:i:s'),    // Heure au format "HH:MM:SS"
            $care->getId()
        );
        if ($conflictingCare !== null && $conflictingCare->getId() !== $care->getId()) {
            return new JsonResponse(['error' => 'Un garde existe déjà pour cette nounou à cette date et dans cet intervalle de temps.'], 400);
        }

        try {
            $em->beginTransaction();
            try {
                // Sauvegarde des anciennes heures pour calculer les différences
                $LastHoursCareCount = $care->getHoursCount();
                if ($meals !== null) {
                    $LastMealsCount = $care->getMealsCount();
                }
                // Mise à jour de la garde
                $care->setDate($startDateTime);
                $care->setMonth($startDateTime->format('Y-m'));
                $care->setStartTime($startDateTime);
                $care->setEndTime($endDateTime);
                if ($meals !== null) {
                    $care->setMealsCount($meals);
                }
                // Calcul du nouveau nombre d'heures
                $interval = $startDateTime->diff($endDateTime);
                $NewHoursCareCount = $interval->h + ($interval->i / 60);
                $care->setHoursCount($this->roundingService->roundToTwoDecimals($NewHoursCareCount));
            } catch (\Throwable $th) {
                return new JsonResponse(['error' => 'Une erreur est survenue lors de la mise à jour de la garde.'], 500);
            }
            try {
                // Taux horaire
                $hoursRate = $nanny->getHourlyRate();
                // Calcul des montants avant/après
                $lastAmountHours = $LastHoursCareCount * $hoursRate;
                $newAmountHours = $NewHoursCareCount * $hoursRate;
                // Mise à jour des totaux dans le paiement mensuel
                $monthlyPayment->setTotalsHours(
                    $this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalsHours() - $LastHoursCareCount + $NewHoursCareCount)
                );
                $monthlyPayment->setAmountHours(
                    $this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountHours() - $lastAmountHours + $newAmountHours)
                );
                // Le montant global total doit refléter le montant des heures
                $totalAmountBefore = $monthlyPayment->getTotalAmount();

                // Gestion si le nombre de repas est modifié
                if ($meals !== null) {
                    $mealRate = $nanny->getMealRate();
                    $lastAmountMeals = $LastMealsCount * $mealRate;
                    $newAmountMeals = $meals * $mealRate;
                    $monthlyPayment->setTotalMeals(
                        $monthlyPayment->getTotalMeals() - $LastMealsCount + $meals
                    );
                    $monthlyPayment->setAmountMeals(
                        $monthlyPayment->getAmountMeals() - $lastAmountMeals + $newAmountMeals
                    );
                    $monthlyPayment->setTotalAmount(
                        $this->roundingService->roundToTwoDecimals($totalAmountBefore - $lastAmountHours + $newAmountHours - $lastAmountMeals + $newAmountMeals)
                    );
                }
                else {
                    $monthlyPayment->setTotalAmount(
                        $this->roundingService->roundToTwoDecimals($totalAmountBefore - $lastAmountHours + $newAmountHours)
                    );
                }
                // Persistance des modifications
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
            'idCare' => $care->getId(),
            'start' => $care->getStartTime(),
            'end' => $care->getEndTime(),
            'meals' => $care->getMealsCount(),
            'monthlyPayment' => [
                'id' => $monthlyPayment->getId(),
                'totalAmount' => $monthlyPayment->getTotalAmount()
            ]
        ]);
    }
}
