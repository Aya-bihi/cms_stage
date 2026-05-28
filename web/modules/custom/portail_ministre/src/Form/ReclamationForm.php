<?php

namespace Drupal\portail_ministre\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Formulaire de soumission d'une réclamation.
 */
class ReclamationForm extends FormBase {

  public function getFormId() {
    return 'portail_ministre_reclamation_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['type_reclamation'] = [
      '#type'     => 'select',
      '#title'    => $this->t('Type de réclamation'),
      '#required' => TRUE,
      '#options'  => [
        'rh'           => 'Ressources Humaines',
        'informatique' => 'Informatique',
        'logistique'   => 'Logistique',
        'administrative' => 'Administrative',
        'autre'        => 'Autre',
      ],
    ];

    $form['exige_demande'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Objet de la réclamation'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => 'Résumez votre réclamation',
    ];

    $form['commentaire'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Description'),
      '#required'    => TRUE,
      '#rows'        => 6,
      '#placeholder' => 'Décrivez votre réclamation en détail...',
    ];

    $form['piece_jointe'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Document justificatif (optionnel)'),
      '#upload_location'   => 'public://reclamations/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf jpg jpeg png doc docx'],
        'file_validate_size'       => [5 * 1024 * 1024],
      ],
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Soumettre la réclamation'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db   = Database::getConnection();
    $user = \Drupal::currentUser();
    $numero = portail_ministre_generer_numero_reclamation();

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

    $id = $db->insert('portail_ministre_demande')
      ->fields([
        'id_utilisateur' => $user->id(),
        'type_demande'   => 'RECLAMATION',
        'numero_ticket'  => $numero,
        'categorie'      => $form_state->getValue('type_reclamation'),
        'exige_demande'  => $form_state->getValue('exige_demande'),
        'commentaire'    => $form_state->getValue('commentaire'),
        'piece_jointe'   => $piece_jointe,
        'statut'         => 'EN_ATTENTE',
        'date_creation'  => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    portail_ministre_journal('RECLAMATION_CREATE',
      'Réclamation ' . $numero . ' créée par ' . $user->getDisplayName());

    portail_ministre_notifier(
      $user->id(),
      'Votre réclamation ' . $numero . ' a été soumise avec succès.',
      '/portail/reclamations/' . $id,
      'reclamation'
    );

    \Drupal::messenger()->addMessage(
      $this->t('Votre réclamation @num a été soumise.', ['@num' => $numero])
    );

    $form_state->setRedirect('portail_ministre.reclamation');
  }

}