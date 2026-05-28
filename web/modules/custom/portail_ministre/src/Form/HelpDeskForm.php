<?php

namespace Drupal\portail_ministre\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Formulaire de soumission d'un ticket Help Desk.
 */
class HelpDeskForm extends FormBase {

  public function getFormId() {
    return 'portail_ministre_helpdesk_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['categorie'] = [
      '#type'     => 'select',
      '#title'    => $this->t('Catégorie'),
      '#required' => TRUE,
      '#options'  => [
        'materiel' => 'Matériel',
        'logiciel' => 'Logiciel',
        'reseau'   => 'Réseau',
        'acces'    => 'Accès / Droits',
        'autre'    => 'Autre',
      ],
    ];

    $form['priorite'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Priorité'),
      '#required'      => TRUE,
      '#default_value' => 'NORMALE',
      '#options'       => [
        'FAIBLE'  => 'Faible',
        'NORMALE' => 'Normale',
        'HAUTE'   => 'Haute',
        'URGENTE' => 'Urgente',
      ],
    ];

    $form['exige_demande'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Objet du ticket'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => 'Résumez votre problème en une ligne',
    ];

    $form['commentaire'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Description détaillée'),
      '#required'    => TRUE,
      '#rows'        => 6,
      '#placeholder' => 'Décrivez votre problème en détail...',
    ];

    $form['piece_jointe'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Pièce jointe (optionnel)'),
      '#upload_location'   => 'public://helpdesk/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf jpg jpeg png doc docx'],
        'file_validate_size'       => [5 * 1024 * 1024],
      ],
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Soumettre le ticket'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $objet = $form_state->getValue('exige_demande');
    if (strlen($objet) < 10) {
      $form_state->setErrorByName('exige_demande', 
        $this->t('L\'objet doit contenir au moins 10 caractères.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db   = Database::getConnection();
    $user = \Drupal::currentUser();

    // Générer numéro ticket
    $numero = portail_ministre_generer_numero_ticket();

    // Gérer pièce jointe
    $piece_jointe = NULL;
    $fids = $form_state->getValue('piece_jointe');
    if (!empty($fids)) {
      $file = \Drupal\file\Entity\File::load(reset($fids));
      if ($file) {
        $file->setPermanent();
        $file->save();
        $piece_jointe = $file->getFileUri();
      }
    }

    // Insérer le ticket
    $id = $db->insert('portail_ministre_demande')
      ->fields([
        'id_utilisateur' => $user->id(),
        'type_demande'   => 'HELPDESK',
        'numero_ticket'  => $numero,
        'categorie'      => $form_state->getValue('categorie'),
        'priorite'       => $form_state->getValue('priorite'),
        'exige_demande'  => $form_state->getValue('exige_demande'),
        'commentaire'    => $form_state->getValue('commentaire'),
        'piece_jointe'   => $piece_jointe,
        'statut'         => 'EN_ATTENTE',
        'date_creation'  => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    // Journal d'audit
    portail_ministre_journal('HELPDESK_CREATE', 
      'Ticket ' . $numero . ' créé par ' . $user->getDisplayName());

    // Notification
    portail_ministre_notifier(
      $user->id(),
      'Votre ticket ' . $numero . ' a été soumis avec succès.',
      '/portail/helpdesk/' . $id,
      'helpdesk'
    );

    \Drupal::messenger()->addMessage(
      $this->t('Votre ticket @num a été soumis avec succès.', 
        ['@num' => $numero])
    );

    $form_state->setRedirect('portail_ministre.helpdesk');
  }

}