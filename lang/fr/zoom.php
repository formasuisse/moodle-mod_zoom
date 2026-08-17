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
$string['recordingretention'] = 'Conservation des enregistrements (jours, vide = valeur du site : {$a})';
$string['recordingretention_help'] = 'Nombre de jours après la séance pendant lesquels les enregistrements cloud de cette activité restent disponibles avant d\'être purgés. Laissez vide pour utiliser la valeur par défaut du site. Indiquez un nombre plus grand pour conserver les enregistrements plus longtemps (p. ex. jusqu\'à une date d\'examen), plus petit pour purger plus tôt, ou 0 pour les conserver indéfiniment.';
$string['err_recordingretention'] = 'La durée de conservation doit être vide (valeur du site) ou un nombre entier de jours.';
// Correction of a community-pack string (not an added string): the AMOS
// French pack renders "Active Speaker" — the video view following whoever
// speaks — as "Haut-parleur actif" (= loudspeaker), which reads as a second
// audio file next to "Audio uniquement". Zoom's own French client says
// "Intervenant actif". NOTE: an installed AMOS pack overrides plugin lang/fr,
// so sites with a French pack in langotherroot still need a fr_local
// override until the fix lands in AMOS.
$string['recordingtype_active_speaker'] = 'Intervenant actif';
$string['recording_available_until'] = 'Disponible jusqu\'au {$a}';
$string['recording_expired'] = 'n\'est plus disponible';
$string['recording_expired_long'] = 'Cet enregistrement n\'est plus disponible : la période de conservation est terminée.';
$string['recording_availability'] = 'Disponibilité';

// Occurrence-first scheduling (#849): the sessions table is the schedule
// surface; "séance" is the deliberate domain word (Zoom's own lingo, EN and
// FR alike, is "occurrence" — trainers say séance).
$string['err_plan_no_host'] = 'Aucun hôte du pool n\'est libre pour toutes les séances planifiées (± intervalle). Modifiez la date ou l\'heure en conflit — ou le pool a besoin d\'un hôte licencié supplémentaire.';
$string['err_plandate_duplicate'] = 'Cette date de séance figure deux fois.';
$string['event_occurrence_conflict'] = 'Conflit de séance Zoom détecté';
$string['firstsession'] = 'Première séance';
$string['occ_add'] = 'Ajouter';
$string['occ_added_notify'] = 'Séance ajoutée.';
$string['occ_addnew'] = 'Nouvelle séance';
$string['occ_cancel'] = 'Annuler';
$string['occ_cancel_confirm'] = 'Annuler la séance du {$a} ? Elle reste affichée comme annulée afin que les étudiant·e·s voient le changement. Son créneau est libéré sur l\'hôte ; l\'annulation est définitive côté Zoom.';
$string['occ_cancelled'] = 'Annulée';
$string['occ_cancelled_notify'] = 'Séance annulée.';
$string['occ_date'] = 'Date';
$string['occ_delete'] = 'Supprimer';
$string['occ_delete_confirm'] = 'Supprimer la séance du {$a} ? Elle disparaît complètement de la liste — à utiliser pour les dates qui n\'ont jamais été réellement planifiées. (Pour annuler visiblement une séance planifiée, utilisez Annuler.)';
$string['occ_deleted_notify'] = 'Séance supprimée.';
$string['occ_err_baddate'] = 'Cette date n\'a pas pu être interprétée.';
$string['occ_err_past'] = 'Les séances passées ne peuvent pas être modifiées.';
$string['occ_moved_notify'] = 'Séance déplacée.';
$string['occ_past'] = 'Passée';
$string['occ_recording'] = 'Enregistrement';
$string['occ_recording_hidden'] = '(masqué pour les étudiant·e·s)';
$string['occ_remove'] = 'Retirer de la liste';
$string['occ_removed_notify'] = 'Séance retirée de la liste.';
$string['occ_status'] = 'Statut';
$string['occ_upcoming'] = 'À venir';
$string['occurrences'] = 'Séances';
$string['occurrencetable'] = 'Afficher le tableau des séances';
$string['occurrencetable_desc'] = 'Mode pool : afficher le tableau des séances (dates, enregistrements, actions de gestion) sur la page de la réunion, à la place de la section Planification.';
$string['plandate'] = 'Séance supplémentaire';
$string['plandatesadd'] = 'Ajouter {no} séances';
$string['plandatesintro'] = 'Planifiez ici toutes les séances de cette réunion — elles partagent un seul lien de connexion et une seule liste d\'enregistrements. Un hôte est réservé pour l\'ensemble du plan à l\'enregistrement ; ensuite, les séances s\'ajoutent, se déplacent ou s\'annulent une à une dans le tableau des séances de la page de la réunion.';
$string['schedulemanagedintable'] = 'L\'horaire de cette réunion se gère séance par séance dans le tableau des séances de la <a href="{$a}">page de la réunion</a>.';
$string['zoomerr_last_occurrence'] = 'La dernière séance restante ne peut pas être annulée — supprimez plutôt l\'activité.';
$string['zoomerr_occurrence_limit'] = 'Cette réunion a atteint le nombre maximal de séances (60, séances annulées comprises). Créez une nouvelle activité pour les séances suivantes.';
$string['zoomerr_pool_nousable'] = 'Le groupe Zoom du pool d\'hôtes « {$a} » n\'a aucun membre utilisable : il est vide, ou aucun de ses membres n\'a de licence alors que seuls les hôtes licenciés peuvent animer (zoom | pooledrequirelicense). Aucune séance ne peut être planifiée tant que ce n\'est pas corrigé.';
