<?php

// Source inspired from DespotifyPHP source example (DespotifyPHP/examples/songlisting.php)

require './DespotifyPHP/src/Despotify.php';
require './auth.php';

// specify how to connect to the gateway
$gateway = '127.0.0.1';
$port = 1234;


// create Despotify object which will be used for all interaction with the gateway(and Spotify, through the gateway)
$ds = new Despotify($gateway, $port);
// attempt to connect
if(!$ds->connect())
{
	die("Could not connect to gateway $gateway on port $port");
}
// attempt to login to Spotify
if(!$ds->login($username, $password))
{
	die('Could not authenticate. Wrong username or password?');
}

// get an array containing ids for all playlists this user has
$playlistArray = $ds->getPlaylistIds();

echo '<?xml version="1.0" encoding="utf-8"?><playlists>';

// iterate over the array of playlist ids
foreach($playlistArray as $playlistId)
{
	// get a Playlist object so that we can start extracting data
	$playlistObject = $ds->getPlaylist($playlistId);
	
	// print the playlist's name
	echo '<playlist><title>' . $playlistObject->getName() . '</title>';

	// get an array containing Track objects, representing the tracks in this playlist
	$trackArray = $playlistObject->getTracks();
	
	
	foreach($trackArray as $trackObject)
	{
		echo '<song>';
			echo '<name>' . $trackObject->getName() . '</name>';
			echo '<spotifyHttpLink>' . $trackObject->getHTTPLink() . '</spotifyHttpLink>';
			echo '<artist>';
			
			// artist name is returned as an array if there are multiple artists performing the song
			if(is_array($trackObject->getArtistName()))
			{
				// flatten the array
				echo implode(', ', $trackObject->getArtistName());
			}
			else
			{
				echo $trackObject->getArtistName();
			}
			
			echo '</artist>';
		echo '</song>';
	}
	echo '</playlist>';
	
	break; // in this example we break here, thus only songs in the first playlist are listed. Because otherwise it might take long time to load the page(with all playlists)
}

echo '</playlists>';
