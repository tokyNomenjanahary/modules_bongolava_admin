<?php

namespace Drupal\bongolava_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;
use Drupal\bongolava_job\Repository\CandidateRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for listing users with role "candidate" and showing details.
 */
final class CandidateController extends ControllerBase {

  public function __construct(
    private readonly CandidateRepository $candidateRepository,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bongolava_job.candidate_repository'),
    );
  }

  public function listAll(): array {
    $request = \Drupal::request();
    $filters = [
      'keyword' => trim((string) ($request->query->get('keyword', '') ?? '')),
      'status' => trim((string) ($request->query->get('status', '') ?? '')),
    ];

    $per_page = 20;

    $count_query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('roles', 'candidate');
    if (!empty($filters['status'])) {
      if ($filters['status'] === 'active') {
        $count_query->condition('status', 1);
      }
      elseif ($filters['status'] === 'blocked') {
        $count_query->condition('status', 0);
      }
    }
    if (!empty($filters['keyword'])) {
      $count_query->condition('mail', '%' . $filters['keyword'] . '%', 'LIKE');
    }
    $total = (int) $count_query->count()->execute();

    $pager = \Drupal::service('pager.manager')->createPager($total, $per_page);
    $current_page = $pager->getCurrentPage();
    $offset = $current_page * $per_page;

    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('roles', 'candidate')
      ->sort('created', 'DESC')
      ->range($offset, $per_page);
    if (!empty($filters['status'])) {
      if ($filters['status'] === 'active') {
        $query->condition('status', 1);
      }
      elseif ($filters['status'] === 'blocked') {
        $query->condition('status', 0);
      }
    }
    if (!empty($filters['keyword'])) {
      $query->condition('mail', '%' . $filters['keyword'] . '%', 'LIKE');
    }

    $uids = $query->execute();
    $users = \Drupal\user\Entity\User::loadMultiple($uids ?: []);

    $header = [
      $this->t('UID'),
      $this->t('Nom'),
      $this->t('Email'),
      $this->t('Statut'),
      $this->t('Actions'),
    ];

    $rows = [];
    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $status_label = $user->isActive() ? $this->t('Actif') : $this->t('Bloqué');

      $view_link = Link::fromTextAndUrl($this->t('Voir'), Url::fromRoute('bongolava_admin.candidate_detail', ['user' => $user->id()]))->toRenderable();
      $view_link['#attributes'] = ['class' => ['button', 'button--small', 'button--primary']];

      $rows[] = [
        'data' => [
          $user->id(),
          $user->getDisplayName(),
          $user->getEmail(),
          $status_label,
          [
            'data' => [$view_link],
          ],
        ],
      ];
    }

    $build = [];
    $build['filter_form'] = $this->formBuilder()->getForm(\Drupal\bongolava_admin\Form\CandidateListFiltersForm::class, $filters);

    if (empty($rows)) {
      $build['empty'] = [
        '#markup' => $this->t('Aucun utilisateur candidat trouvé.'),
      ];
    }
    else {
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }

    $build['pager'] = ['#type' => 'pager'];
    $build['#attached'] = ['library' => ['bongolava_admin/moderation']];
    $build['#title'] = $this->t('Liste des candidats');

    return $build;
  }

  public function detail(UserInterface $user): array {
    $profile = $this->candidateRepository->loadByUser($user->id());
    $rows = [
      [$this->t('UID'), $user->id()],
      [$this->t('Nom'), $user->getDisplayName()],
      [$this->t('Email'), $user->getEmail()],
      [$this->t('Téléphone'), $profile['phone'] ?? ''],
      [$this->t('Adresse'), $profile['address'] ?? ''],
      [$this->t('Localisation'), $profile['location'] ?? ''],
      [$this->t('Âge'), $profile['age'] ?? ''],
      [$this->t('Niveau d\'expérience'), $profile['experience_level'] ?? ''],
      [$this->t('Compétences'), $profile['skills'] ?? ''],
      [$this->t('Bio'), $profile['bio'] ?? ''],
      [$this->t('Créé le'), $this->formatProfileDate($profile['created_at'] ?? '')],
    ];

    // Render a simple HTML table to avoid render-array nesting issues
    $html = '<table class="bongolava-admin-table">';
    foreach ($rows as $r) {
      $html .= '<tr><td>' . $r[0] . '</td><td>' . htmlspecialchars((string) $r[1], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    $html .= '</table>';
    $build = [
      '#markup' => \Drupal\Core\Render\Markup::create($html),
      '#title' => $this->t('Détails candidat : @name', ['@name' => $user->getDisplayName()]),
    ];

    // Photo display
    if (!empty($profile['photo_path'])) {
      $photo_path = $profile['photo_path'];
      // Normalize if stored as "photos/filename.jpg" or just "filename.jpg".
      if (str_starts_with($photo_path, 'photos/')) {
        $photo_path = substr($photo_path, strlen('photos/'));
      }
      $public_uri = 'public://bongolava_job/photos/' . $photo_path;
      $photo_url = file_create_url($public_uri);
      $img = '<img src="' . htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($user->getDisplayName(), ENT_QUOTES, 'UTF-8') . '" style="max-width:200px; height:auto;" />';
      $html .= '<table class="bongolava-admin-table"><tr><td>' . $this->t('Photo') . '</td><td>' . $img . '</td></tr></table>';
    }

    // CV display
    if (!empty($profile['cv_path'])) {
      $cv_path = $profile['cv_path'];
      if (str_starts_with($cv_path, 'cvs/')) {
        $cv_path = substr($cv_path, strlen('cvs/'));
      }
      $public_uri = 'public://bongolava_job/cvs/' . $cv_path;
      $cv_url = file_create_url($public_uri);
      // Build a simple safe anchor HTML to avoid Url objects ending up in
      // attributes (which can cause rendering errors). Use Markup to mark
      // it as safe HTML.
      $anchor = '<a href="' . htmlspecialchars($cv_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="button">' . $this->t('Télécharger le CV') . '</a>';
      $html .= '<table class="bongolava-admin-table"><tr><td>' . $this->t('CV') . '</td><td>' . $anchor . '</td></tr></table>';
    }

    // Toggle form
    $form = $this->formBuilder()->getForm(\Drupal\bongolava_admin\Form\CandidateToggleStatusForm::class, $user->id());
    $rendered_form = \Drupal::service('renderer')->renderRoot($form);
    $html .= '<div class="bongolava-admin-actions">' . $rendered_form . '</div>';
    $build['#markup'] = \Drupal\Core\Render\Markup::create($html);

    $build['#attached'] = ['library' => ['bongolava_admin/moderation']];
    return $build;
  }

  /**
   * Format a profile date value (string or timestamp) into a human readable date.
   */
  private function formatProfileDate(string|int|null $value): string {
    if (empty($value)) {
      return '';
    }
    // If already an integer timestamp, use it. Otherwise try strtotime.
    if (is_numeric($value)) {
      $ts = (int) $value;
    }
    else {
      $ts = strtotime((string) $value);
      if ($ts === FALSE) {
        return (string) $value;
      }
    }
    // Use Drupal date.formatter service to format according to site language.
    $day = (int) date('j', $ts);
    $monthIndex = (int) date('n', $ts);
    $year = (int) date('Y', $ts);
    $months = [
      1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
      7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $monthName = $months[$monthIndex] ?? date('F', $ts);
    return $day . ' ' . $monthName . ' ' . $year;
  }

  public function detailTitle(User $user): string {
    return (string) $this->t('Candidat : @name', ['@name' => $user->getDisplayName()]);
  }

}
