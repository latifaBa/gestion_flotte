<?php
function envoyerMailAffectation($agentEmail, $agentNom, $nd, $etablissement, $fonction) {
    $sujet = "Affectation d'un numéro de flotte";

    $message = "Bonjour $agentNom,\n\n";
    $message .= "Vous venez d'être affecté au numéro : $nd\n";
    $message .= "Établissement : $etablissement\n";
    $message .= "Fonction : $fonction\n";
    $message .= "\nMerci de prendre connaissance de cette affectation.\n";

    $headers = "From: gestion-flotte@domaine.com\r\n" .
               "Reply-To: gestion-flotte@domaine.com\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    if (filter_var($agentEmail, FILTER_VALIDATE_EMAIL)) {
        return mail($agentEmail, $sujet, $message, $headers);
    }
    return false;
}
