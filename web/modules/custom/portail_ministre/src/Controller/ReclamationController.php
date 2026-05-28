<?php

namespace Drupal\portail_ministre\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

/**
 * Controller E-Réclamations — Portail Ministère.
 */
class ReclamationController extends ControllerBase {

  /**
   * Liste des réclamations.
   */
  public function liste() {
    $db = Database::getConnection();
    $user = \Drupal::currentUser();
    $is_admin = $user->hasPermission('administer portail reclamation');

    $query = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('type_demande', 'RECLAMATION')
      ->orderBy('date_creation', 'DESC');

    if (!$is_admin) {
      $query->condition('id_utilisateur', $user->id());
    }

    $reclamations = $query->execute()->fetchAll();

    foreach ($reclamations as $r) {
      $demandeur = \Drupal\user\Entity\User::load($r->id_utilisateur);
      $r->nom_demandeur = $demandeur ? $demandeur->getDisplayName() : 'Inconnu';
    }

    return [
      '#theme'        => 'reclamation_liste',
      '#reclamations' => $reclamations,
      '#user_role'    => $is_admin ? 'admin' : 'agent',
    ];
  }

  /**
   * Détail d'une réclamation.
   */
  public function detail($id) {
    $db = Database::getConnection();
    $user = \Drupal::currentUser();

    $reclamation = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->condition('type_demande', 'RECLAMATION')
      ->execute()
      ->fetchObject();

    if (!$reclamation) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $is_admin = $user->hasPermission('administer portail reclamation');
    if (!$is_admin && $reclamation->id_utilisateur != $user->id()) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $demandeur = \Drupal\user\Entity\User::load($reclamation->id_utilisateur);
    $reclamation->nom_demandeur = $demandeur ? $demandeur->getDisplayName() : 'Inconnu';

    return [
      '#theme'       => 'reclamation_detail',
      '#reclamation' => $reclamation,
    ];
  }

  /**
   * Vue admin — toutes les réclamations.
   */
  public function admin() {
    $db = Database::getConnection();

    $reclamations = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('type_demande', 'RECLAMATION')
      ->orderBy('date_creation', 'DESC')
      ->execute()
      ->fetchAll();

    foreach ($reclamations as $r) {
      $demandeur = \Drupal\user\Entity\User::load($r->id_utilisateur);
      $r->nom_demandeur = $demandeur ? $demandeur->getDisplayName() : 'Inconnu';
    }

    return [
      '#theme'        => 'reclamation_liste',
      '#reclamations' => $reclamations,
      '#user_role'    => 'admin',
    ];
  }

}