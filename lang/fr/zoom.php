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
$string['zoomerr_pool_noregistrationhost'] = 'L\'inscription nécessite un hôte Zoom licencié et le pool n\'en a aucun. Désactivez l\'inscription pour cette réunion, ou ajoutez un hôte licencié au pool.';
$string['zoomerr_pool_exhausted_slots'] = 'Aucun hôte du pool n\'est libre (± intervalle) pour : {$a}. Modifiez ces dates ou heures — ou le pool a besoin d\'un hôte licencié supplémentaire.';
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

// Occurrence-first scheduling (#849): the occurrences table is the schedule
// surface. Wording rule (Urs 2026-08-17): Zoom things use Zoom lingo
// ("occurrence", "réunion") — "séance" is reserved for FormaSuisse course-term
// sessions (e.g. the spring session of the HRSE GRH course).
$string['err_plandate_duplicate'] = 'Cette date d\'occurrence figure deux fois.';
$string['event_occurrence_conflict'] = 'Conflit d\'occurrence Zoom détecté';
$string['firstsession'] = 'Première occurrence';
$string['occ_add'] = 'Ajouter';
$string['occ_added_notify'] = 'Occurrence ajoutée.';
$string['occ_cancel'] = 'Annuler';
$string['occ_cancel_confirm_btn'] = 'Confirmer l\'annulation';
$string['occ_cancel_confirm'] = 'Annuler l\'occurrence du {$a} ? Elle reste affichée comme annulée afin que les étudiant·e·s voient le changement. Son créneau est libéré sur l\'hôte ; l\'annulation est définitive côté Zoom.';
$string['occ_cancelled'] = 'Annulée';
$string['occ_cancelled_notify'] = 'Occurrence annulée.';
$string['occ_date'] = 'Date';
$string['occ_duration'] = 'Durée';
$string['occ_dateformat'] = 'JJ/MM/AAAA';
$string['occ_discard'] = 'Supprimer';
$string['occ_discard_confirm_btn'] = 'Confirmer la suppression';
$string['occ_discard_confirm'] = 'Supprimer l\'occurrence du {$a} ? Elle disparaît complètement de la liste — à utiliser pour les dates qui n\'ont jamais été réellement planifiées. (Pour annuler visiblement une occurrence planifiée, utilisez Annuler.)';
$string['occ_discarded_notify'] = 'Occurrence supprimée.';
$string['occ_err_baddate'] = 'Cette date n\'a pas pu être interprétée.';
$string['occ_err_past'] = 'Les occurrences passées ne peuvent pas être modifiées.';
$string['occ_moved_notify'] = 'Occurrence déplacée.';
$string['occ_past'] = 'Passée';
$string['occ_planner_addrow'] = 'Ajouter une ligne';
$string['occ_planner_daily'] = '+5j';
$string['occ_planner_daily_help'] = 'Ajoute 5 occurrences quotidiennes à la suite de cette ligne (jours consécutifs, mêmes heure et durée)';
$string['occ_planner_monthly'] = '+5m';
$string['occ_planner_monthly_help'] = 'Ajoute 5 occurrences toutes les 4 semaines à la suite de cette ligne (même jour de semaine, mêmes heure et durée)';
$string['occ_planner_weekly'] = '+5s';
$string['occ_planner_weekly_help'] = 'Ajoute 5 occurrences hebdomadaires à la suite de cette ligne (même jour de semaine, mêmes heure et durée)';
$string['occ_recording'] = 'Enregistrement';
$string['occ_rec_hide'] = 'Masquer';
$string['occ_rec_show'] = 'Afficher';
$string['recordingsharingfailed'] = 'La visibilité de l\'enregistrement a été enregistrée, mais Zoom n\'a pas pu être mis à jour : les participant·e·s ne peuvent peut-être pas encore le lire. Réessayez.';
$string['occ_recording_started'] = 'Enregistrement démarré à {$a}';
$string['occ_hide'] = 'Retirer de la liste';
$string['occ_revert'] = 'Rétablir';
$string['occ_hidden_notify'] = 'Occurrence retirée de la liste.';
$string['occ_time'] = 'Heure';
$string['occ_status'] = 'Statut';
$string['occ_upcoming'] = 'À venir';
$string['occurrences'] = 'Occurrences';
$string['occurrencetable'] = 'Afficher le tableau des occurrences';
$string['occurrencetable_desc'] = 'Mode pool : afficher le tableau des occurrences (dates, enregistrements, actions de gestion) sur la page de la réunion, à la place de la section Planification.';
$string['plandate'] = 'Occurrence supplémentaire';
$string['plandatesadd'] = 'Ajouter {no} occurrences';
$string['plandatesintro'] = 'Planifiez ici toutes les occurrences de cette réunion — elles partagent un seul lien de connexion et une seule liste d\'enregistrements. Un hôte est réservé pour l\'ensemble du plan à l\'enregistrement ; ensuite, les occurrences s\'ajoutent, se déplacent ou s\'annulent une à une dans le tableau des occurrences de la page de la réunion. Laissez une date vide pour ignorer la ligne.';
$string['schedulemanagedintable'] = 'L\'horaire de cette réunion se gère occurrence par occurrence dans le tableau des occurrences de la <a href="{$a}">page de la réunion</a>.';
$string['zoomerr_last_occurrence'] = 'La dernière occurrence restante ne peut pas être annulée — supprimez plutôt l\'activité.';
$string['zoomerr_occurrence_limit'] = 'Cette réunion a atteint le nombre maximal d\'occurrences (60, occurrences annulées comprises). Créez une nouvelle activité pour les occurrences suivantes.';
$string['zoomerr_pool_nousable'] = 'Le groupe Zoom du pool d\'hôtes « {$a} » n\'a aucun membre utilisable : il est vide, ou aucun de ses membres n\'a de licence alors que seuls les hôtes licenciés peuvent animer (zoom | pooledrequirelicense). Aucune occurrence ne peut être planifiée tant que ce n\'est pas corrigé.';
