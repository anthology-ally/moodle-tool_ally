<?php
// This file is part of Moodle - http://moodle.org/
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
 * @copyright  Copyright (c) 2020 Blackboard Inc. (http://www.blackboard.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adminurl'] = 'URL di avvio';
$string['adminurldesc'] = 'L\'URL di avvio LTI usato per accedere al report sull\'accessibilità.';
$string['allyclientconfig'] = 'Configurazione Ally';
$string['ally:clientconfig'] = 'Accedere alla configurazione client e aggiornarla';
$string['ally:viewlogs'] = 'Visualizzatore di log Ally';
$string['clientid'] = 'ID cliente';
$string['clientiddesc'] = 'ID client Ally';
$string['code'] = 'Codice';
$string['contentauthors'] = 'Autori di contenuti';
$string['contentauthorsdesc'] = 'L\'accessibilità dei file del corso caricati degli amministratori e utenti assegnati a questi specifici ruoli sarà valutata. Verrà assegnato ai file un voto per l\'accessibilità. I voti bassi indicano che il file deve essere modificato per essere più accessibile.';
$string['contentupdatestask'] = 'Attività di aggiornamento dei contenuti';
$string['curlerror'] = 'Errore cURL: {$a}';
$string['curlinvalidhttpcode'] = 'Codice stato HTTP non valido: {$a}';
$string['curlnohttpcode'] = 'Impossibile verificare il codice stato HTTP';
$string['error:invalidcomponentident'] = 'Identificatore componente non valido {$a}';
$string['error:pluginfilequestiononly'] = 'Solo i componenti della domanda sono supportati per questo URL';
$string['error:componentcontentnotfound'] = 'Contenuti non trovati per {$a}';
$string['error:wstokenmissing'] = 'Manca il token del servizio Web. Può essere necessario che un amministratore esegua la configurazione automatica?';
$string['filecoursenotfound'] = 'Il file non appartiene a nessun corso';
$string['fileupdatestask'] = 'Forza gli aggiornamenti dei file in Ally';
$string['id'] = 'ID';
$string['key'] = 'Chiave';
$string['keydesc'] = 'La chiave utente LTI.';
$string['level'] = 'Livello';
$string['message'] = 'Messaggio';
$string['pluginname'] = 'Ally';
$string['pushurl'] = 'URL aggiornamenti file';
$string['pushurldesc'] = 'Forza le notifiche sugli aggiornamenti dei file in questo URL.';
$string['queuesendmessagesfailure'] = 'Si è verificato un errore durante l\'invio dei messaggi a AWS SQS. Dati errore: $a';
$string['secret'] = 'Secret';
$string['secretdesc'] = 'Il segreto LTI.';
$string['showdata'] = 'Mostra dati';
$string['hidedata'] = 'Nascondi dati';
$string['showexplanation'] = 'Mostra spiegazione';
$string['hideexplanation'] = 'Nascondi spiegazione';
$string['showexception'] = 'Mostra eccezione';
$string['hideexception'] = 'Nascondi eccezione';
$string['usercapabilitymissing'] = 'L\'utente fornito non è in grado di eliminare questo file.';
$string['autoconfigure'] = 'Configura automaticamente il servizio Web Ally';
$string['autoconfiguredesc'] = 'Crea automaticamente il ruolo e dell\'utente del servizio Web per Ally.';
$string['autoconfigureconfirmation'] = 'Crea automaticamente il ruolo del servizio Web per Ally e consenti il servizio Web. Saranno intraprese le seguenti azioni: <ul><li>creazione di un ruolo chiamato \'ally_webservice\' e di un utente con il nome \'ally_webuser\'</li><li>aggiunta dell\'utente \'ally_webuser\' nel ruolo \'ally_webservice\'</li><li>attivazione dei servizi Web</li><li>attivazione del resto del protocollo del servizio Web</li><li>attivazione del servizio Web Ally</li><li>creazione di un token per l\'account \'ally_webuser\'</li></ul>';
$string['autoconfigsuccess'] = 'Successo - il servizio Web Ally è stato configurato automaticamente.';
$string['autoconfigtoken'] = 'Il token del servizio Web è il seguente:';
$string['autoconfigapicall'] = 'È possibile testare che il servizio Web funzioni tramite il seguente URL:';
$string['privacy:metadata:files:action'] = 'L\'azione intrapresa sul file, EG: creato, aggiornato o eliminato.';
$string['privacy:metadata:files:contenthash'] = 'L\'hash del contenuto del file per determinare il carattere univoco.';
$string['privacy:metadata:files:courseid'] = 'L\'ID del corso al quale appartiene il file.';
$string['privacy:metadata:files:externalpurpose'] = 'Per integrarsi con Ally, i file devono essere scambiati con Ally.';
$string['privacy:metadata:files:filecontents'] = 'I contenuti attuali del file vengono inviati ad Ally per valutarne l\'accessibilità.';
$string['privacy:metadata:files:mimetype'] = 'Il tipo di file MIME, EG: testo/semplice, immagine/jpeg, ecc.';
$string['privacy:metadata:files:pathnamehash'] = 'L\'hash del nome del percorso del file per identificarlo in modo univoco.';
$string['privacy:metadata:files:timemodified'] = 'L\'ora dell\'ultima modifica al file.';
$string['cachedef_request'] = 'Cache della richiesta di filtro Ally';
$string['pushfilessummary'] = 'Riepilogo degli aggiornamenti file Ally.';
$string['pushfilessummary:explanation'] = 'Riepilogo degli aggiornamenti file inviati ad Ally.';
$string['section'] = 'Sezione {$a}';
$string['lessonanswertitle'] = 'Risposta per la lezione "{$a}"';
$string['lessonresponsetitle'] = 'Risultato per la lezione "{$a}"';
$string['logs'] = 'Log Ally';
$string['logrange'] = 'Intervallo registro';
$string['loglevel:none'] = 'Nessuno/a';
$string['loglevel:light'] = 'Light';
$string['loglevel:medium'] = 'Medio';
$string['loglevel:all'] = 'Tutti';
$string['logger:pushtoallysuccess'] = 'Forzato con successo al punto finale Ally';
$string['logger:pushtoallyfail'] = 'Push all\'end point Ally non riuscito';
$string['logger:pushfilesuccess'] = 'Forzatura dei file al punto finale Ally riuscita';
$string['logger:pushfileliveskip'] = 'Impossibile forzare il file in tempo reale';
$string['logger:pushfileliveskip_exp'] = 'Forzatura dei file in tempo reale saltata a causa di problemi di comunicazione. La forzatura del file in tempo reale sarà ripristinata quando l\'attività degli aggiornamenti dei file sarà riuscita. Esaminare la configurazione.';
$string['logger:pushfileserror'] = 'Push all\'end point Ally non riuscito';
$string['logger:pushfileserror_exp'] = 'Errori associati alla forzatura degli aggiornamenti dei contenuti ai servizi Ally.';
$string['logger:pushcontentsuccess'] = 'Forzatura del contenuto al punto finale Ally riuscita';
$string['logger:pushcontentliveskip'] = 'Errore di forzatura dei contenuti in tempo reale';
$string['logger:pushcontentliveskip_exp'] = 'La forzatura dei contenuti in tempo reale è stata saltata a causa di problemi di comunicazione. La forzatura dei contenuti in tempo reale sarà ripristinata quando l\'attività di aggiornamento dei contenuti avverrà con successo. Esaminare la configurazione.';
$string['logger:pushcontentserror'] = 'Push all\'end point Ally non riuscito';
$string['logger:pushcontentserror_exp'] = 'Errori associati alla forzatura degli aggiornamenti dei contenuti ai servizi Ally.';
$string['logger:addingconenttoqueue'] = 'Aggiunta dei contenuti alla coda di forzatura';
$string['logger:annotationmoderror'] = 'Annotazione dei contenuti del modulo Ally non riuscita.';
$string['logger:annotationmoderror_exp'] = 'Il modulo non è stato identificato correttamente.';
$string['logger:failedtogetcoursesectionname'] = 'Impossibile ottenere il nome della sezione del corso';
$string['logger:cmidresolutionfailure'] = 'Impossibile risolvere l\'ID del modulo del corso';
$string['courseupdatestask'] = 'Forza eventi del corso in Ally';
$string['logger:pushcoursesuccess'] = 'Forzatura successiva degli eventi del corso al punto di fine di Ally';
$string['logger:pushcourseliveskip'] = 'Errore di forzatura evento corso in tempo reale';
$string['logger:pushcourseerror'] = 'Errore di forzatura evento corso in tempo reale';
$string['logger:pushcourseliveskip_exp'] = 'Gli eventi del corso in tempo reale non vengono riprodotti a causa di problemi di comunicazione. L\'evento del corso pubblico sarà ripristinato quando l\'attività di aggiornamento degli eventi avverrà successo. Rivedere la configurazione.';
$string['logger:pushcourseserror'] = 'Push all\'end point Ally non riuscito';
$string['logger:pushcourseserror_exp'] = 'Gli errori associati agli aggiornamenti del corso rimandano ai servizi Ally.';
$string['logger:addingcourseevttoqueue'] = 'Aggiunta dell\'evento del corso per forzare la coda';
$string['logger:cmiderraticpremoddelete'] = 'L\'ID del modulo del corso riscontra dei problemi a eliminare questo elemento.';
$string['logger:cmiderraticpremoddelete_exp'] = 'Il modulo non è stato identificato correttamente. Il modulo è inesistente poiché è stato eliminato oppure è presente un fattore che ha attivato l\'eliminazione e pertanto non è stato trovato.';
$string['logger:servicefailure'] = 'Non andato a buon fine durante la fruizione del servizio.';
$string['logger:servicefailure_exp'] = '<br>Classe {$a->class}<br>Parametri: {$a->params}';
$string['logger:autoconfigfailureteachercap'] = 'Non andato a buon fine durante l\'assegnazione a un docente di una capacità archetipo al ruolo ally_webservice.';
$string['logger:autoconfigfailureteachercap_exp'] = '<br>Capacità: {$a->cap}<br>Permesso: {$a->permission}';
