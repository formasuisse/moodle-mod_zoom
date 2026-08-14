<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French strings for the Fork feature (see README.md).
 *
 * Only the strings ADDED by the patch live here. All other French strings come
 * from Moodle's community-maintained language pack (translate.moodle.org),
 * which every site downloads into its dataroot — upstream plugins ship English
 * only. Those packs cannot know about our added strings, so without this file
 * the new messages would fall back to English for French users.
 *
 * @package    mod_zoom
 * @copyright  2026 FormaSuisse SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['event_collision_imminent'] = 'Collision imminente sur un hôte Zoom';
$string['event_overrun_detected'] = 'Dépassement de séance Zoom détecté';
$string['event_pool_exhausted'] = 'Pool d\'hôtes Zoom épuisé';
$string['event_pool_misconfigured'] = 'Pool d\'hôtes Zoom mal configuré';
$string['event_registration_dropped'] = 'Paramètres d\'inscription Zoom supprimés';
$string['registration_automoodle'] = 'Oui — les participant·e·s sont inscrit·e·s automatiquement avec leur nom Moodle et rejoignent pré-identifié·e·s (lien personnel, aucun formulaire)';
$string['registration_done'] = 'Votre inscription à cette séance est confirmée. Le bouton pour rejoindre sera disponible à l\'heure prévue.';
$string['registration_self'] = 'Manuelle — les participant·e·s confirment leur présence par un clic S\'inscrire avant de rejoindre (inscription avec leur nom Moodle)';
$string['teacher'] = 'Formateur·trice';
$string['teacher_help'] = 'La personne qui anime cette séance : elle seule voit le bouton Démarrer la réunion. L\'hôte côté Zoom est choisi automatiquement dans le pool d\'hôtes.';
$string['zoomerr_pool_exhausted'] = 'Aucun hôte Zoom n\'est disponible pour ce créneau : chaque hôte du pool a une réservation en conflit (y compris l\'intervalle requis entre les séances). Déplacez la séance ou ajoutez un hôte au pool.';
$string['zoomerr_pool_misconfigured'] = 'Le groupe Zoom du pool d\'hôtes « {$a} » n\'existe pas ou ne peut pas être lu. Aucune séance ne peut être planifiée tant que ce n\'est pas corrigé.';
$string['zoomerr_registration_dropped'] = 'Zoom a accepté l\'enregistrement mais a silencieusement supprimé les paramètres d\'inscription : la séance n\'a PAS été enregistrée comme configurée. Causes connues : l\'hôte n\'a pas de licence Zoom (siège), la séance a été convertie en réunion personnelle (PMI) de l\'hôte, ou un conflit d\'exigences d\'authentification. Corrigez la cause puis enregistrez à nouveau.';
$string['purgerecordings'] = 'Purger les enregistrements Zoom expirés';
$string['recordingretentiondays'] = 'Durée de conservation des enregistrements (jours)';
$string['recordingretentiondays_help'] = 'Si défini, les enregistrements cloud sont déplacés vers la corbeille Zoom et retirés de Moodle ce nombre de jours après la séance — tout lien de visionnage partagé cesse alors de fonctionner. 0 désactive la purge.';
$string['recording_available_until'] = 'Disponible jusqu\'au {$a}';
$string['recording_expired'] = 'n\'est plus disponible';
$string['recording_expired_long'] = 'Cet enregistrement n\'est plus disponible : la période de conservation est terminée.';
$string['recording_availability'] = 'Disponibilité';
