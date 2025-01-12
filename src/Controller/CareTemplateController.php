<?php

namespace App\Controller;

use App\Entity\Nanny;
use App\Entity\CareTemplate;
use App\Form\CareTemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/care-template/', name: 'app_care_template_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CareTemplateController extends AbstractController
{

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
}
