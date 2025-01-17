<?php

namespace App\Controller;

use App\Entity\Care;
use App\Entity\Nanny;
use App\Entity\CareTemplate;
use App\Entity\MonthlyPayment;
use App\Form\CareTemplateType;
use App\Service\RoundingService;
use App\Repository\CareRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MonthlyPaymentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/care-template/', name: 'app_care_template_')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class CareTemplateController extends AbstractController
{
    private RoundingService $roundingService;

    public function __construct(
        RoundingService $roundingService
    )
    {
        $this->roundingService = $roundingService;
    }

    #[Route('index', name: 'index', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager
    ): Response {
        $templates = $entityManager->getRepository(CareTemplate::class)->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('care_template/index.html.twig', [
            'templates' => $templates
        ]);
    }

    #[Route('new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $template = new CareTemplate();
        $template->setUser($this->getUser());

        $form = $this->createForm(CareTemplateType::class, $template, [
            'user' => $this->getUser()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les données du weekSchedule depuis la requête
            $weekSchedule = $request->request->all()['weekSchedule'] ?? [];
            $formattedSchedule = [];

            foreach ($weekSchedule as $day => $config) {
                $formattedSchedule[$day] = [
                    'isActive' => filter_var($config['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'slots' => array_map(function ($slot) {
                        return [
                            'startTime' => $slot['startTime'],
                            'endTime' => $slot['endTime'],
                            'mealsCount' => (int)$slot['mealsCount']
                        ];
                    }, $config['slots'] ?? [])
                ];
            }

            $template->setWeekSchedule($formattedSchedule);

            if (!$this->validateTemplate($template)) {
                return $this->render('care_template/new.html.twig', [
                    'form' => $form
                ]);
            }

            $entityManager->persist($template);
            $entityManager->flush();

            $this->addFlash('success', 'Le template a été créé avec succès');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('care_template/new.html.twig', [
            'form' => $form
        ]);
    }

    private function validateTemplate(CareTemplate $template): bool
    {
        $weekSchedule = $template->getWeekSchedule();
        $daysTranslation = [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi'
        ];

        foreach ($weekSchedule as $day => $config) {
            if (!$config['isActive']) {
                continue;
            }

            if (empty($config['slots'])) {
                $this->addFlash('error', sprintf('Le %s doit avoir au moins un créneau si activé', $daysTranslation[$day]));
                return false;
            }

            foreach ($config['slots'] as $slot) {
                $start = new \DateTime($slot['startTime']);
                $end = new \DateTime($slot['endTime']);

                if ($start >= $end) {
                    $this->addFlash('error', sprintf('Pour le %s, l\'heure de fin doit être après l\'heure de début', $daysTranslation[$day]));
                    return false;
                }

                if ($slot['mealsCount'] < 0 || $slot['mealsCount'] > 3) {
                    $this->addFlash('error', sprintf('Pour le %s, le nombre de repas doit être entre 0 et 3', $daysTranslation[$day]));
                    return false;
                }
            }

            // Vérifier le chevauchement des créneaux
            $slots = $config['slots'];
            usort($slots, fn($a, $b) => strtotime($a['startTime']) - strtotime($b['startTime']));

            for ($i = 0; $i < count($slots) - 1; $i++) {
                $currentEnd = new \DateTime($slots[$i]['endTime']);
                $nextStart = new \DateTime($slots[$i + 1]['startTime']);

                if ($currentEnd > $nextStart) {
                    $this->addFlash('error', sprintf('Pour le %s, les créneaux ne peuvent pas se chevaucher', $daysTranslation[$day]));
                    return false;
                }
            }
        }

        return true;
    }

    #[Route('delete/{slug}', name: 'delete', methods: ['GET'])]
    public function delete(
        CareTemplate $template,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérification de la propriété du template
        if ($template->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce template');
        }

        // Suppression du template
        $entityManager->remove($template);
        $entityManager->flush();

        // Message de confirmation
        $this->addFlash('success', 'Le template a été supprimé avec succès');

        // Redirection
        return $this->redirectToRoute('app_care_template_index');
    }

    #[Route('show/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        CareTemplate $template
    ): Response {
        // Vérification de la propriété du template
        if ($template->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce template');
        }

        return $this->render('care_template/show.html.twig', [
            'template' => $template,
            'days' => [
                'monday' => 'Lundi',
                'tuesday' => 'Mardi',
                'wednesday' => 'Mercredi',
                'thursday' => 'Jeudi',
                'friday' => 'Vendredi'
            ]
        ]);
    }

    #[Route('apply/{slug}', name: 'apply', methods: ['GET'])]
    public function apply(
        CareTemplate $template,
        Request $request,
        EntityManagerInterface $entityManager,
        CareRepository $careRepository,
        MonthlyPaymentRepository $monthlyPaymentRepository
    ): Response {
        // Vérification de la propriété du template
        if ($template->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas appliquer ce template');
        }

        $user = $this->getUser();

        // Création des gardes
        $nanny = $template->getNanny();
        if (!$nanny instanceof Nanny) {
            throw $this->createNotFoundException('La nounou n\'a pas été trouvée');
        }

        // Récupérer la date de début de semaine
        $weekStart = new \DateTime($request->query->get('start'));
        $currentDate = $weekStart->format('Y-m-d');
        $weekSchedule = $template->getWeekSchedule();
        // Mapping des jours de la semaine
        $dayMapping = [
            'monday' => 0,
            'tuesday' => 1,
            'wednesday' => 2,
            'thursday' => 3,
            'friday' => 4
        ];

        try {
            $entityManager->beginTransaction();
            // Créer les gardes pour chaque jour actif
            foreach ($weekSchedule as $day => $config) {
                if ($config['isActive'] && !empty($config['slots'])) {
                    // Calculer la date pour ce jour
                    $date = clone $weekStart;
                    $date->modify('+' . $dayMapping[$day] . ' days');

                    // Créer une garde pour chaque créneau
                    foreach ($config['slots'] as $slot) {
                        // Créer les DateTime pour le début et la fin
                        $startTime = new \DateTime($date->format('Y-m-d') . ' ' . $slot['startTime']);
                        $endTime = new \DateTime($date->format('Y-m-d') . ' ' . $slot['endTime']);

                        $conflictingCare = $careRepository->findConflictingCare(
                            $nanny->getId(),
                            $date->format('Y-m-d'), // Date au format "YYYY-MM-DD"
                            $startTime->format('H:i:s'), // Heure au format "HH:MM:SS"
                            $endTime->format('H:i:s')    // Heure au format "HH:MM:SS"
                        );
                        if ($conflictingCare) {
                            $entityManager->rollback();
                            $this->addFlash('error', 'Un garde existe déjà pour cette nounou à cette date et dans cet intervalle de temps.');
                            return $this->redirectToRoute('app_care_index', [
                                'slug' => $nanny->getSlug(),
                                'currentDate' => $currentDate
                            ]);
                        }

                        // Calculer les heures
                        $hours = $this->roundingService->roundToTwoDecimals(($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600);

                        try {
                            $care = new Care();
                            $care->setUser($this->getUser());
                            $care->setNanny($nanny);
                            $care->setDate($date);
                            $care->setStartTime($startTime);
                            $care->setEndTime($endTime);
                            $care->setMealsCount($slot['mealsCount']);
                            $care->setHoursCount($hours);
                            $care->setCreatedAt(new \DateTime());
                            $care->setMonth($date->format('Y-m'));
                            $slugbase = $date->format('Y-m-d') . '-' . $startTime->format('H:i') . '-' . $endTime->format('H:i');
                            $care->setSlugBase($slugbase);
                            $entityManager->persist($care);
                        } catch (\Throwable $th) {
                            $this->addFlash('error', 'Une erreur est survenue lors de la création de la garde');
                            $entityManager->rollback();
                            return $this->redirectToRoute('app_care_index', [
                                'slug' => $nanny->getSlug(),
                                'currentDate' => $currentDate
                            ]);
                        }

                        try {
                            $month = $care->getMonth();
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
                            $this->addFlash('error', 'Une erreur est survenue lors de la récupération du paiement mensuel');
                            $entityManager->rollback();
                            return $this->redirectToRoute('app_care_index', [
                                'slug' => $nanny->getSlug(),
                                'currentDate' => $currentDate
                            ]);
                        }

                        try {
                            // Mise à jour des totaux
                            $monthlyPayment->setTotalsHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalsHours() + $hours));
                            $monthlyPayment->setTotalMeals($monthlyPayment->getTotalMeals() + $care->getMealsCount());
                            $monthlyPayment->setAmountHours($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalsHours() * $nanny->getHourlyRate()));
                            $monthlyPayment->setAmountMeals($this->roundingService->roundToTwoDecimals($monthlyPayment->getTotalMeals() * $nanny->getMealRate()));
                            $monthlyPayment->setTotalAmount($this->roundingService->roundToTwoDecimals($monthlyPayment->getAmountHours() + $monthlyPayment->getAmountMeals()));

                            $entityManager->persist($monthlyPayment);
                        } catch (\Throwable $th) {
                            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour du paiement mensuel');
                            $entityManager->rollback();
                            return $this->redirectToRoute('app_care_index', [
                                'slug' => $nanny->getSlug(),
                                'currentDate' => $currentDate
                            ]);
                        }

                        try {
                            $entityManager->flush();
                        } catch (\Throwable $th) {
                            $this->addFlash('error', 'Une erreur est survenue lors de la sauvegarde de la garde');
                            $entityManager->rollback();
                            return $this->redirectToRoute('app_care_index', [
                                'slug' => $nanny->getSlug(),
                                'currentDate' => $currentDate
                            ]);
                        }
                    }
                }
            }

            $entityManager->commit();
        } catch (\Throwable $th) {
            $entityManager->rollback();
            $this->addFlash('error', 'Une erreur est survenue lors de l\'application du template');
            return $this->redirectToRoute('app_care_index', [
                'slug' => $nanny->getSlug(),
                'currentDate' => $currentDate
            ]);
        }

        $this->addFlash('success', 'Le template a été appliqué avec succès');
        return $this->redirectToRoute('app_care_index', [
            'slug' => $nanny->getSlug(),
            'currentDate' => $currentDate
        ]);
    }
}
