<?php

namespace Drupal\portail_ministre\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Formulaire de changement de statut d'un ticket (admin/technicien).
 */
class HelpDeskStatutForm extends FormBase {

  public function getFormId() {
    return 'portail_ministre_helpdesk_statut_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $db = Database::getConnection();

    $ticket = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$ticket) {
      $form['error'] = ['#markup' => $this->t('Ticket introuvable.')];
      return $form;
    }

    $form['ticket_id'] = [
      '#type'  => 'hidden',
      '#value' => $id,
    ];

    $form['info'] = [
      '#markup' => '<p><strong>Ticket :</strong> ' . 
                   htmlspecialchars($ticket->numero_ticket) . 
                   ' — ' . htmlspecialchars($ticket->exige_demande) . '</p>',
    ];

    $form['statut'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Nouveau statut'),
      '#required'      => TRUE,
      '#default_value' => $ticket->statut,
      '#options'       => [
        'EN_ATTENTE' => 'En attente',
        'VALIDATION' => 'En cours de traitement',
        'REFUS'      => 'Résolu',
        'FERME'      => 'Fermé',
      ],
    ];

    // Charger les techniciens (utilisateurs avec permission admin)
    $form['id_technicien'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Assigner à un technicien'),
      '#default_value' => $ticket->id_technicien ?? '',
      '#options'       => $this->getTechniciens(),
      '#empty_option'  => '-- Non assigné --',
    ];

    $form['commentaire_admin'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Commentaire du technicien'),
      '#rows'  => 4,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Mettre à jour'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * Retourne la liste des techniciens.
   */
  private function getTechniciens() {
    $users = \Drupal\user\Entity\User::loadMultiple();
    $options = [];
    foreach ($users as $user) {
      if ($user->hasPermission('administer portail helpdesk')) {
        $options[$user->id()] = $user->getDisplayName();
      }
    }
    return $options;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db   = Database::getConnection();
    $user = \Drupal::currentUser();
    $id   = $form_state->getValue('ticket_id');

    // Récupérer le ticket avant modification
    $ticket = $db->select('portail_ministre_demande', 'd')
      ->fields('d')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    $nouveau_statut    = $form_state->getValue('statut');
    $id_technicien     = $form_state->getValue('id_technicien');
    $commentaire_admin = $form_state->getValue('commentaire_admin');

    // Construire les champs à mettre à jour
    $fields = [
      'statut'       => $nouveau_statut,
      'id_technicien' => $id_technicien ?: NULL,
    ];

    // Ajouter commentaire si renseigné
    if (!empty($commentaire_admin)) {
      $fields['commentaire'] = $ticket->commentaire . 
        "\n\n[" . date('d/m/Y H:i') . ' - ' . $user->getDisplayName() . '] ' . 
        $commentaire_admin;
    }

    // Date de résolution si résolu
    if ($nouveau_statut === 'REFUS') {
      $fields['date_resolution'] = \Drupal::time()->getRequestTime();
    }

    $db->update('portail_ministre_demande')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();

    // Journal
    portail_ministre_journal('HELPDESK_UPDATE',
      'Statut ticket ' . $ticket->numero_ticket . 
      ' changé en ' . $nouveau_statut . ' par ' . $user->getDisplayName(),
      $id
    );

    // Notifier le demandeur
    portail_ministre_notifier(
      $ticket->id_utilisateur,
      'Votre ticket ' . $ticket->numero_ticket . 
      ' a été mis à jour. Nouveau statut : ' . $nouveau_statut,
      '/portail/helpdesk/' . $id,
      'helpdesk'
    );

    \Drupal::messenger()->addMessage(
      $this->t('Le ticket a été mis à jour avec succès.')
    );

    $form_state->setRedirect('portail_ministre.helpdesk.admin');
  }

}