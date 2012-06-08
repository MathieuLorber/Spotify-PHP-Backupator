<?php

// Source inspired from DespotifyPHP source example (DespotifyPHP/examples/songlisting.php)

require './conf.php';

require './DespotifyPHP/src/Despotify.php';

$backupDir = 'backup-' . time();
mkdir($backupDir);
$backupFilename = $backupDir . '/backup.xml';

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

// write in backup file
function w($string) {
	global $fileHandle;
	
	fwrite($fileHandle, $string);
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
	f('Stops at ' . $limit);
}
f('Writes in dir ' . $backupDir);

$i = 1;

//new SimpleXMLElement
foreach($playlistIds as $playlistId) {
	$playlistXmlData = $ds->getPlaylistXmlData($playlistId);
	
	$baseDir = $backupDir . '/' . $playlistId;
	mkdir($baseDir);
	file_put_contents($baseDir . '/playlist.xml', $playlistXmlData);

	$playlistObject = new Playlist(new SimpleXMLElement($playlistXmlData), $ds->getConnection());

	$s = '<playlist>';
	$s .= '<id>' . $playlistId . '</id>';
	$s .= '<name>' . $playlistObject->getName() . '</name>';

	$trackIds = $playlistObject->getTrackIds();
	$s .= '<tracks>';

	foreach($trackIds as $trackId) {
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
		$s .= '<name>' . $trackObject->getName() . '</name>';
		$s .= '<album>' . $trackObject->getAlbumName() . '</album>';

		// artist name is returned as an array if there are multiple artists performing the song
		if(is_array($trackObject->getArtistName())) {
			$s .= '<artists>';
			foreach($trackObject->getArtistName() as $artist) {
				$s .= '<artist>' . $artist . '</artist>';
			}
			$s .= '<artists>';
		} else {
			$s .= '<artist>' . $trackObject->getArtistName() . '</artist>';
		}
		
		$s .= '<length>' . $trackObject->getLength() . '</length>';
		$s .= '</track>';
	}
	$s .= '</tracks></playlist>';

	w($s);

	f('* ' . $i . ' / ' . $playlistsCount);
	$i++;
	if($limit != -1 && $i > $limit) {
		break;
	}
}

w('</playlists>');
fclose($fileHandle);
f('File ' . $backupFilename . ' finished');
f('Backup took '. (time() - $beginTime) . 's');
