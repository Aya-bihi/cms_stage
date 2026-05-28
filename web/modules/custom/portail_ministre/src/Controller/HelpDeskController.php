<?php

namespace Drupal\portail_ministre\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller Help Desk — Portail Ministère.
 */
class HelpDeskController extends ControllerBase {

  /**
   * Liste des tickets de l'agent connecté.
   */
  public function liste() {
    $db = Database::getConnection();
    $user = \Drupal::currentUser();
    $is_admin = $user->hasPermission('administer portail helpdesk');

    $query = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('type_demande', 'HELPDESK')
      ->orderBy('date_creation', 'DESC');

    if (!$is_admin) {
      $query->condition('id_utilisateur', $user->id());
    }

    $tickets = $query->execute()->fetchAll();

    return [
      '#theme'      => 'helpdesk_liste',
      '#tickets'    => $tickets,
      '#user_role'  => $is_admin ? 'admin' : 'agent',
      '#attached'   => [
        'library' => ['portail_ministre/helpdesk'],
      ],
    ];
  }

  /**
   * Détail d'un ticket.
   */
  public function detail($id) {
    $db = Database::getConnection();
    $user = \Drupal::currentUser();

    $ticket = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->condition('type_demande', 'HELPDESK')
      ->execute()
      ->fetchObject();

    if (!$ticket) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Vérifier accès : agent voit ses tickets, admin voit tout
    $is_admin = $user->hasPermission('administer portail helpdesk');
    if (!$is_admin && $ticket->id_utilisateur != $user->id()) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Charger le nom du technicien si assigné
    if (!empty($ticket->id_technicien)) {
      $technicien = \Drupal\user\Entity\User::load($ticket->id_technicien);
      $ticket->nom_technicien = $technicien ? $technicien->getDisplayName() : 'Non assigné';
    }
    else {
      $ticket->nom_technicien = 'Non assigné';
    }

    // Charger le nom du demandeur
    $demandeur = \Drupal\user\Entity\User::load($ticket->id_utilisateur);
    $ticket->nom_demandeur = $demandeur ? $demandeur->getDisplayName() : 'Inconnu';

    return [
      '#theme'   => 'helpdesk_detail',
      '#ticket'  => $ticket,
      '#attached' => [
        'library' => ['portail_ministre/helpdesk'],
      ],
    ];
  }

  /**
   * Vue admin — tous les tickets.
   */
  public function admin() {
    $db = Database::getConnection();

    $tickets = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('type_demande', 'HELPDESK')
      ->orderBy('priorite', 'DESC')
      ->orderBy('date_creation', 'DESC')
      ->execute()
      ->fetchAll();

    // Statistiques
    $stats = [
      'total'      => 0,
      'nouveau'    => 0,
      'en_cours'   => 0,
      'resolu'     => 0,
      'urgents'    => 0,
    ];

    foreach ($tickets as $t) {
      $stats['total']++;
      if ($t->statut === 'EN_ATTENTE') $stats['nouveau']++;
      if ($t->statut === 'VALIDATION') $stats['en_cours']++;
      if ($t->statut === 'REFUS')      $stats['resolu']++;
      if ($t->priorite === 'URGENTE')  $stats['urgents']++;

      // Charger noms
      $demandeur = \Drupal\user\Entity\User::load($t->id_utilisateur);
      $t->nom_demandeur = $demandeur ? $demandeur->getDisplayName() : 'Inconnu';
    }

    return [
      '#theme'     => 'helpdesk_liste',
      '#tickets'   => $tickets,
      '#user_role' => 'admin',
      '#stats'     => $stats,
      '#attached'  => [
        'library' => ['portail_ministre/helpdesk'],
      ],
    ];
  }
public function changerStatut($id, $statut) {
    $db = Database::getConnection();
    $db->update('portail_ministre_demande')
      ->fields(['statut' => $statut])
      ->condition('id', $id)
      ->execute();
    \Drupal::messenger()->addMessage('Statut mis à jour.');
    return $this->redirect('portail_ministre.helpdesk.admin');
  }
}