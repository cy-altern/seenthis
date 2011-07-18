<?php
function _traiter_hash ($regs) {
	$aff_tag = mb_substr($regs[0], 1, 1000);
	$tag = addslashes(mb_strtolower($aff_tag, "UTF-8"));
	
	$url = "?page=test_hash&amp;tag=$tag";
	
	$query = sql_query("SELECT id_mot FROM spip_mots WHERE titre='$tag' AND id_groupe=1");
	if ($row = sql_fetch($query)) {
		$id_mot = $row["id_mot"];
		
		include_spip("urls/arbo");
		$url = declarer_url_arbo("mot", $id_mot);
	}
	
	return "<span class='lien_tag'>#<a href='$url'>$aff_tag</a></span>";
	
	
}

function _traiter_people ($regs) {
	$tag = mb_substr($regs[0], 1, 1000);
	
	$query = sql_query("SELECT id_auteur FROM spip_auteurs WHERE login='$tag'");
	if ($row = sql_fetch($query)) {
		$id_auteur = $row["id_auteur"];
		
		include_spip("urls/arbo");
		$url = declarer_url_arbo("auteur", $id_auteur);
	}
	if ($url) return "<span class='lien_people'>@<a href='$url'>$tag</a></span>";
	else return "@$tag";
//	else return "<span class='lien_people'>@<span class='inexistant'>$tag</span></span>";
	
	
}



function _creer_lien_riche($lien) {

	
	$lien_or = $lien;
	
	$favicon = recuperer_favicon($lien);
	if ($favicon) $style = " style='background-image:url($favicon);'";

	// Supprimer slash final
	$lien = preg_replace(",/$,", "", $lien);

	// Si c'est une image, ne pas afficher le lien
	if (preg_match(",\.(png|gif|jpg)$,", $lien)) {
		list($width, $height) = @getimagesize($lien);
		if (($width * $height) >= 300) return;
	}

	
	$query = sql_query("SELECT id_syndic, lang, titre, url_syndic FROM spip_syndic WHERE url_site=\"$lien\"");
	if ($row = sql_fetch($query)) {
		$id_syndic = $row["id_syndic"];	
		$lang = $row["lang"];
		$titre = $row["titre"];
		$long = $row["url_syndic"];
		
		if (strlen($long) > 0) {
			$lien_or = $long;
			// $lien = $long;
		}
		
		if (strlen($lang) > 1){
			//$nom_lang = traduire_nom_langue($lang);
			$lang = " hreflang=\"$lang\"";
		}
		if (strlen($titre) > 3) {
			$titre = textebrut($titre);
			$titre = str_replace('"', "'", $titre);
			$titre = " title=\"$titre\"";
		}
			
	}
	
	if ($id_syndic) {
		// Ne faire apparaître le lien_court
		// que si plusieurs billets referencent le lien
		$query_total = sql_query("SELECT spip_me.* FROM spip_me, spip_me_syndic 
			WHERE spip_me_syndic.id_syndic=$id_syndic  AND spip_me_syndic.id_me=spip_me.id_me AND spip_me.statut='publi'
			LIMIT 1,1");
		while ($row = sql_fetch($query_total)) {
			$total = "<span class='lien_lien_total'><a href='sites/$id_syndic'>►</a></span>";
		}
	} else {
		$total = "";
	}
	
	
	include_spip("inc/lien_court");
	$intitule = sucrer_utm(lien_court($lien, 45));
	
	
	
	$le_lien = "<span class='lien_lien'$style>$total<a href=\"$lien_or\" class='spip_out'$titre$lang>$intitule</a></span>";

	$le_lien = str_replace("&", "&amp;", $le_lien);
	$le_lien = str_replace("&amp;amp;", "&amp;", $le_lien);

	return $le_lien;
}


function _traiter_lien ($regs) {
	$lien = $regs[0];
	
	// Supprimer parenthese fermante finale si pas de parenthese ouvrante dans l'URL
	$retour_parenthese = "";
	if (preg_match(",\)$,", $lien) && !preg_match(",\(,", $lien)) {
		$lien = preg_replace(",\)$,", "", $lien);
		$retour_parenthese = ")";
	}

	
	$le_lien = _creer_lien_riche($lien);	
	
	$GLOBALS["num_lien"] ++;
	
	$GLOBALS["les_liens"][$GLOBALS["num_lien"]] = $le_lien;
	
	return "XXX_LIEN_".$GLOBALS["num_lien"]."_LIEN_XXX$retour_parenthese";
}

function _traiter_lien_retablir($regs) {
	$num = $regs[1];
	
	return $GLOBALS["les_liens"][$num];
}
function _traiter_blocs_retablir($regs) {
	$num = $regs[1];
	
	return trim($GLOBALS["les_blocs"][$num]);
}

function _traiter_block ($regs) {
	$texte = $regs[2];
	
	$texte = preg_replace(",[\x{275d}\x{275e}],u", "", $texte);

	// Cas pathologique: des blocs dans des blocs
	$texte = preg_replace_callback(",XXX_LIEN_([0-9]+)_BLOC_XXX,Uums", "_traiter_blocs_retablir", $texte);
	$texte = preg_replace(",<\/?blockquote[^>]*>,", "", $texte);
	
	$final = "";

	if (preg_match(",[»\x{201d}\"]([»\x{201d}\"])$,Uums", $texte, $fin) ) {
		$final = $fin[1];
		
		$texte = preg_replace(",[»\x{201d}\"]$,Uums", "", $texte);
	}


	$texte = str_replace("~", "TILDE_SEENTHIS", $texte);
	
	$lang = detecter_langue($texte);
	if ($lang) {
		$dir = lang_dir($lang);
		lang_select($lang);
		$texte = typo($texte);
		lang_select();
	} else {
		$texte = typo($texte);
	}
	
	$texte = str_replace("TILDE_SEENTHIS", "~", $texte);
	
	
	if ($lang) $inserer = " lang=\"$lang\" dir=\"$dir\"";
	
	$le_bloc = "\n\n<blockquote$inserer><p> $texte </p></blockquote>\n\n";
	$GLOBALS["num_bloc"] ++;
	
	$GLOBALS["les_blocs"][$GLOBALS["num_bloc"]] = $le_bloc;
	
	return "\n\nXXX_LIEN_".$GLOBALS["num_bloc"]."_BLOC_XXX\n\n".$final;
}

function _traiter_texte($texte) {

	include_spip("inc/texte");
//	include_spip("php/detecter_langue_fonctions");
	
	$texte = preg_replace(",\r,", "\n", $texte);
	
	$texte = str_replace("<", "&lt;", $texte);
	$texte = str_replace(">", "&gt;", $texte);



	// Echapper les URL
	// (parce que les URL peuvent contenir des «#» qui deviendraient des tags
	$texte = preg_replace_callback("/"._REG_URL."/ui", "_traiter_lien", $texte);

	// Remplacer les people
	$texte = preg_replace_callback("/"._REG_PEOPLE."/i", "_traiter_people", $texte);
	
	// Remplacer les tags
	$texte = preg_replace_callback("/"._REG_HASH."/ui", "_traiter_hash", $texte);



	$texte = trim($texte);
	
	$texte = preg_replace(",\n\-,", "\n–", $texte);
	$texte = preg_replace(",^\-,", "–", $texte);


	// Extraire les citations (paragraphe commençant et se terminant par un «»)

	$texte = preg_replace_callback(",()[[:space:]]?(\x{275d}[^\x{275e}]*\x{275e})()[[:space:]]?(),Uums", "_traiter_block", $texte);


	// Retester sur paragraphe multiple
	$texte = preg_replace_callback(",(\n+|^)[[:space:]]?([«\x{201c}\"][^«\x{201c}\"]*[»\x{201d}\"])()[[:space:]]?(\n+|$),Uums", "_traiter_block", $texte);
	// Tester d'abord sur paragraphes simples
	$texte = preg_replace_callback(",(\n+|^)[[:space:]]?([«\x{201c}\"][^\n]*[»\x{201d}\"])()[[:space:]]?(\n+|$),Uums", "_traiter_block", $texte);
	// Retester sur paragraphe multiple
	$texte = preg_replace_callback(",(\n+|^)[[:space:]]?([«\x{201c}\"].*[»\x{201d}\"])()[[:space:]]?(\n+|$),Uums", "_traiter_block", $texte);

	// Supprimer dans une variable temporaire les mentions XXX, de facon a recuprer seulement le texte
	$texte_racine = preg_replace(",XXX_[A-Z]+_([0-9]+)_[A-Z]+_XXX,Uums", "", $texte);
	$lang = detecter_langue($texte_racine);
	
	$texte = str_replace("~", "TILDE_SEENTHIS", $texte);
	
	if ($lang) {
		$dir = lang_dir($lang);
		lang_select($lang);
		$texte = typo($texte);
		lang_select();
	} else {
		$texte = typo($texte);
	}
	$texte = str_replace("TILDE_SEENTHIS", "~", $texte);
	
	// Remettre les infos des liens
	$texte = preg_replace_callback(",XXX_LIEN_([0-9]+)_BLOC_XXX,Uums", "_traiter_blocs_retablir", $texte);
	$texte = preg_replace_callback(",XXX_LIEN_([0-9]+)_LIEN_XXX,", "_traiter_lien_retablir", $texte);

	// Detacher les blocs du reste du texte, afin de bien fermer et ouvrir les paragraphes.
	$texte = str_replace("<blockquote>", "\n\n<blockquote>", $texte);
	$texte = str_replace("</blockquote>", "</blockquote>\n\n", $texte);
	
	$texte = "<p>$texte</p>";

	$texte = preg_replace(",([[:space:]]?)\n\n+,", "</p><p>", $texte);
	$texte = preg_replace(",<p>([[:space:]]?)<\/p>,", "", $texte);
	$texte = preg_replace(",<p><blockquote([^>]*)>,", "<blockquote$1>", $texte);
	$texte = preg_replace(",</blockquote>[\n\r\t\ ],", "</blockquote>", $texte);
	$texte = str_replace("</blockquote></p>", "</blockquote>", $texte);
	
	
	
	$texte = preg_replace(",([[:space:]]?)(\n|\r),", "<br />", $texte);
	$texte = str_replace("<p><br />", "<p>", $texte);

	if ($lang) $inserer = " lang=\"$lang\" dir=\"$dir\"";

	return "<div$inserer>$texte</div>";
}
?>