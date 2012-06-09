<?php

// Source inspired from DespotifyPHP source example (DespotifyPHP/examples/songlisting.php)

require './conf.php';

require './DespotifyPHP/src/Despotify.php';

$backupDir = 'backup-' . time();
mkdir($backupDir);
$backupFilename = $backupDir . '/backup.xml';
$rawDir = $backupDir . '/raw';
mkdir($rawDir);

define('EOL', "\r\n");

$beginTime = time();

$ds = new Despotify($gateway, $port);
if(!$ds->connect())
{
	die("Could not connect to gateway $gateway on port $port");
}
if(!$ds->login($username, $password))
{
	die('Could not authenticate. Wrong username or password?');
}

$fileHandle = fopen($backupFilename, 'w');

// write in "main" backup file
function w($string) {
	global $fileHandle;

	fwrite($fileHandle, $string);
}

// clean names for xml
function c($string) {
	$string = str_replace('&', '&amp;', $string);
	$string = str_replace('<', '&lt;', $string); // cause I <3 U SO
	$string = str_replace('>', '&gt;', $string);
	$string = str_replace('"', '&quot;', $string);
	$string = str_replace('\'', '&apos;', $string);
	return $string;
}

// flush
function f($string) {
	echo $string . EOL;
	ob_flush();
}

$playlistIds = $ds->getPlaylistIds();
file_put_contents($backupDir . '/playlists-order.csv', implode($playlistIds, ';'));
$playlistsCount = count($playlistIds);

$s = '<?xml version="1.0" encoding="utf-8"?>';
$s .= '<playlists origin="spotify" user="' . $username . '" number="' . $playlistsCount . '">';
w($s);

f('Begin listing');
f($playlistsCount . ' playlists for user ' . $username);
if($limit != -1) {
	f('Stops at ' . $limit . ' iterations');
}
f('Writes in dir ' . $backupDir);

$iteration = 1;
// this one begins to be incremented when beginId is reached
$runningIteration = 1;
// is used if not null
$beginId = NULL;

$isRunning = false;
if($beginId == NULL) {
	$isRunning = true;
} else {
	f('Begins at id : ' . $beginId);
}
foreach($playlistIds as $playlistId) {
	if($beginId != NULL && $playlistId == $beginId) {
		$isRunning = true;
	}
	if(!$isRunning) {
		$iteration++;
		continue;
	}
	if($limit != -1 && $runningIteration > $limit) {
		break;
	}
	f('* ' . $iteration . ' / ' . $playlistsCount . '  -  ' . $playlistId);
	$iteration++;
	$runningIteration++;

	$playlistXmlData = $ds->getPlaylistXmlData($playlistId);
	
	$baseDir = $rawDir . '/' . $playlistId;
	mkdir($baseDir);
	file_put_contents($baseDir . '/playlist.xml', $playlistXmlData);

	$playlistObject = new Playlist(new SimpleXMLElement($playlistXmlData), $ds->getConnection());

	$s = '<playlist>';
	$s .= '<id>' . $playlistId . '</id>';
	$s .= '<name>' . c($playlistObject->getName()) . '</name>';

	$trackIds = $playlistObject->getTrackIds();
	
	$s .= '<tracks>';
	foreach($trackIds as $trackId) {
		// it happens when playlist is empty !
		if(empty($trackId)) {
			continue;
		}
		$trackXmlData = $ds->getTrackXmlData($trackId);
		file_put_contents($baseDir. '/' . $trackId . '.xml', $trackXmlData);
		
		$trackXmlObject = new SimpleXMLElement($trackXmlData);
		// dirty fix as in Track.php
		if(is_array($trackXmlObject)) {
			$trackXmlObject = $trackXmlObject[0];
		}
		$trackObject = new Track($trackXmlObject, $ds->getConnection());	
	
		$s .= '<track>';
		$s .= '<id>' . $trackObject->getId() . '</id>';
		$s .= '<spotifyId>' . toSpotifyId($trackObject->getId()) . '</spotifyId>';
		$s .= '<name>' . c($trackObject->getName()) . '</name>';
		$s .= '<album>' . c($trackObject->getAlbumName()) . '</album>';

		// artist name is returned as an array if there are multiple artists performing the song
		if(is_array($trackObject->getArtistName())) {
			$s .= '<artists>';
			foreach($trackObject->getArtistName() as $artist) {
				$s .= '<artist>' . c($artist) . '</artist>';
			}
			$s .= '</artists>';
		} else {
			$s .= '<artist>' . c($trackObject->getArtistName()) . '</artist>';
		}
		
		$s .= '<length>' . $trackObject->getLength() . '</length>';
		$s .= '</track>';
	}
	$s .= '</tracks></playlist>';

	w($s);
}

w('</playlists>');
fclose($fileHandle);
f('File ' . $backupFilename . ' finished');
f('Backup took '. (time() - $beginTime) . 's');
