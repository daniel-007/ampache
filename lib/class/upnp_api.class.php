<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/**
 * UPnP Class
 *
 * This class wrap Ampache to UPnP API functions.
 * These are all static calls.
 *
 */
class Upnp_Api
{
    # UPnP classes:
    # object.item.audioItem
    # object.item.imageItem
    # object.item.videoItem
    # object.item.playlistItem
    # object.item.textItem
    # object.container

    /**
     * constructor
     * This really isn't anything to do here, so it's private
     */
    private function __construct()
    {
    }

    private static function udpSend($buf, $delay=15, $host="239.255.255.250", $port=1900)
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $buf, strlen($buf), 0, $host, $port);
        socket_close($socket);
        usleep($delay*1000);
    }

    public static function sddpSend($delay=15, $host="239.255.255.250", $port=1900)
    {
        $uuidStr = '2d8a2e2b-7869-4836-a9ec-76447d620734';
        $strHeader  = 'NOTIFY * HTTP/1.1' . "\r\n";
        $strHeader .= 'HOST: ' . $host . ':' . $port . "\r\n";
        $strHeader .= 'LOCATION: http://' . AmpConfig::get('http_host') . ':'. AmpConfig::get('http_port') . AmpConfig::get('raw_web_path') . '/upnp/MediaServerServiceDesc.php' . "\r\n";
        $strHeader .= 'SERVER: DLNADOC/1.50 UPnP/1.0 Ampache/3.7' . "\r\n";
        $strHeader .= 'CACHE-CONTROL: max-age=1800' . "\r\n";
        $strHeader .= 'NTS: ssdp:alive' . "\r\n";
        $rootDevice = 'NT: upnp:rootdevice' . "\r\n";
        $rootDevice .= 'USN: uuid:' . $uuidStr . '::upnp:rootdevice' . "\r\n". "\r\n";

        $buf = $strHeader . $rootDevice;
        self::udpSend($buf, $delay, $host, $port);

        $uuid = 'NT: uuid:' . $uuidStr . "\r\n";
        $uuid .= 'USN: uuid:' . $uuidStr . "\r\n". "\r\n";
        $buf = $strHeader . $uuid;
        self::udpSend($buf, $delay, $host, $port);

        $deviceType = 'NT: urn:schemas-upnp-org:device:MediaServer:1' . "\r\n";
        $deviceType .= 'USN: uuid:' . $uuidStr . '::urn:schemas-upnp-org:device:MediaServer:1' . "\r\n". "\r\n";
        $buf = $strHeader . $deviceType;
        self::udpSend($buf, $delay, $host, $port);

        $serviceCM = 'NT: urn:schemas-upnp-org:service:ConnectionManager:1' . "\r\n";
        $serviceCM .= 'USN: uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ConnectionManager:1' . "\r\n". "\r\n";
        $buf = $strHeader . $serviceCM;
        self::udpSend($buf, $delay, $host, $port);

        $serviceCD = 'NT: urn:schemas-upnp-org:service:ContentDirectory:1' . "\r\n";
        $serviceCD .= 'USN: uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ContentDirectory:1' . "\r\n". "\r\n";
        $buf = $strHeader . $serviceCD;
        self::udpSend($buf, $delay, $host, $port);
    }

    public static function parseUPnPRequest($prmRequest)
    {
        $reader = new XMLReader();
        $reader->XML($prmRequest);
        while ($reader->read()) {
            if (($reader->nodeType == XMLReader::ELEMENT) && !$reader->isEmptyElement) {
                switch ($reader->localName) {
                    case 'Browse':
                        $retArr['action'] = 'browse';
                        break;
                    case 'Search':
                        $retArr['action'] = 'search';
                        break;
                    case 'ObjectID':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['objectid'] = $reader->value;
                        } # end if
                        break;
                    case 'BrowseFlag':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['browseflag'] = $reader->value;
                        } # end if
                        break;
                    case 'Filter':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['filter'] = $reader->value;
                        } # end if
                        break;
                    case 'StartingIndex':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['startingindex'] = $reader->value;
                        } # end if
                        break;
                    case 'RequestedCount':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['requestedcount'] = $reader->value;
                        } # end if
                        break;
                    case 'SearchCriteria':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                          $retArr['searchcriteria'] = $reader->value;
                        } # end if
                        break;
                    case 'SortCriteria':
                        $reader->read();
                        if ($reader->nodeType == XMLReader::TEXT) {
                            $retArr['sortcriteria'] = $reader->value;
                        } # end if
                        break;
                } # end switch
            } # end if
        } #end while
        return $retArr;
    } #end function


    public static function createDIDL($prmItems)
    {
        # TODO: put object.container in container tags where they belong. But as long as the WDTVL doesn't mind... ;)
        # $prmItems is an array of arrays
        $xmlDoc = new DOMDocument('1.0', 'utf-8');
        $xmlDoc->formatOutput = true;

        # Create root element and add namespaces:
        $ndDIDL = $xmlDoc->createElementNS('urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/', 'DIDL-Lite');
        $ndDIDL->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $ndDIDL->setAttribute('xmlns:upnp', 'urn:schemas-upnp-org:metadata-1-0/upnp/');
        $xmlDoc->appendChild($ndDIDL);

        # Return empty DIDL if no items present:
        if ( (!isset($prmItems)) || ($prmItems == '') ) {
            return $xmlDoc;
        } # end if

        # Add each item in $prmItems array to $ndDIDL:
        foreach ($prmItems as $item) {
            if ($item['upnp:class']	== 'object.container') {
                $ndItem = $xmlDoc->createElement('container');
            } else {
                $ndItem = $xmlDoc->createElement('item');
            }
            $useRes = false;
            $ndRes = $xmlDoc->createElement('res');
            $ndRes_text = $xmlDoc->createTextNode($item['res']);
            $ndRes->appendChild($ndRes_text);

            # Add each element / attribute in $item array to item node:
            foreach ($item as $key => $value) {
                # Handle attributes. Better solution?
                switch ($key) {
                    case 'id':
                        $ndItem->setAttribute('id', $value);
                        break;
                    case 'parentID':
                        $ndItem->setAttribute('parentID', $value);
                        break;
                    case 'childCount':
                        $ndItem->setAttribute('childCount', $value);
                        break;
                    case 'restricted':
                        $ndItem->setAttribute('restricted', $value);
                        break;
                    case 'res':
                        break;
                    case 'duration':
                        $ndRes->setAttribute('duration', $value);
                        $useRes = true;
                        break;
                    case 'size':
                        $ndRes->setAttribute('size', $value);
                        $useRes = true;
                        break;
                    case 'bitrate':
                        $ndRes->setAttribute('bitrate', $value);
                        $useRes = true;
                        break;
                    case 'protocolInfo':
                        $ndRes->setAttribute('protocolInfo', $value);
                        $useRes = true;
                        break;
                    case 'resolution':
                        $ndRes->setAttribute('resolution', $value);
                        $useRes = true;
                        break;
                    case 'colorDepth':
                        $ndRes->setAttribute('colorDepth', $value);
                        $useRes = true;
                        break;
                    default:
                        $ndTag = $xmlDoc->createElement($key);
                        $ndItem->appendChild($ndTag);
                        # check if string is already utf-8 encoded
                        $ndTag_text = $xmlDoc->createTextNode((mb_detect_encoding($value,'auto')=='UTF-8')?$value:utf8_encode($value));
                        $ndTag->appendChild($ndTag_text);
                } # end switch
                if ($useRes) {
                    $ndItem->appendChild($ndRes);
                }
            } # end foreach
            $ndDIDL->appendChild($ndItem);
        } # end foreach
        return $xmlDoc;
    } # end function


    public static function createSOAPEnvelope($prmDIDL, $prmNumRet, $prmTotMatches, $prmResponseType = 'u:BrowseResponse', $prmUpdateID = '0')
    {
        # $prmDIDL is DIDL XML string
        # XML-Layout:
        #
        #		-s:Envelope
        #				-s:Body
        #						-u:BrowseResponse
        #								Result (DIDL)
        #								NumberReturned
        #								TotalMatches
        #								UpdateID
        #
        $doc  = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;
        $ndEnvelope = $doc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 's:Envelope');
        $doc->appendChild($ndEnvelope);
        $ndBody = $doc->createElement('s:Body');
        $ndEnvelope->appendChild($ndBody);
        $ndBrowseResp = $doc->createElementNS('urn:schemas-upnp-org:service:ContentDirectory:1', $prmResponseType);
        $ndBody->appendChild($ndBrowseResp);
        $ndResult = $doc->createElement('Result',$prmDIDL);
        $ndBrowseResp->appendChild($ndResult);
        $ndNumRet = $doc->createElement('NumberReturned', $prmNumRet);
        $ndBrowseResp->appendChild($ndNumRet);
        $ndTotMatches = $doc->createElement('TotalMatches', $prmTotMatches);
        $ndBrowseResp->appendChild($ndTotMatches);
        $ndUpdateID = $doc->createElement('UpdateID', $prmUpdateID); # seems to be ignored by the WDTVL
        #$ndUpdateID = $doc->createElement('UpdateID', (string) mt_rand(); # seems to be ignored by the WDTVL
        $ndBrowseResp->appendChild($ndUpdateID);

        Return $doc;
    }

    public static function _musicMetadata($prmPath, $prmQuery = '')
    {
        $root = 'amp://music';
        $pathreq = explode('/', $prmPath);
        if ($pathreq[0] == '' && count($pathreq) > 0) {
            array_shift($pathreq);
        }

        $meta = null;
        switch ($pathreq[0]) {
            case 'artists':
                switch (count($pathreq)) {
                    case 1:
                        $counts = Catalog::count_songs();
                        $meta = array(
                            'id'			=> $root . '/artists',
                            'parentID'		=> $root,
                            'childCount'	=> $counts['artists'],
                            'dc:title'		=> T_('Artists'),
                            'upnp:class'	=> 'object.container',
                        );
                    break;

                    case 2:
                        $artist = new Artist($pathreq[1]);
                        if ($artist->id) {
                            $artist->format();
                            $meta = self::_itemArtist($artist, $root . '/artists');
                        }
                    break;
                }
            break;

            case 'albums':
                switch (count($pathreq)) {
                    case 1:
                        $counts = Catalog::count_songs();
                        $meta = array(
                            'id'			=> $root . '/albums',
                            'parentID'		=> $root,
                            'childCount'	=> $counts['albums'],
                            'dc:title'		=> T_('Albums'),
                            'upnp:class'	=> 'object.container',
                        );
                    break;

                    case 2:
                        $album = new Album($pathreq[1]);
                        if ($album->id) {
                            $album->format();
                            $meta = self::_itemAlbum($album, $root . '/albums');
                        }
                    break;
                }
            break;

            case 'songs':
                switch (count($pathreq)) {
                    case 1:
                        $counts = Catalog::count_songs();
                        $meta = array(
                            'id'			=> $root . '/songs',
                            'parentID'		=> $root,
                            'childCount'	=> $counts['songs'],
                            'dc:title'		=> T_('Songs'),
                            'upnp:class'	=> 'object.container',
                        );
                    break;

                    case 2:
                        $song = new Song($pathreq[1]);
                        if ($song->id) {
                            $song->format();
                            $meta = self::_itemSong($song, $root . '/songs');
                        }
                    break;
                }
            break;

            case 'playlists':
                switch (count($pathreq)) {
                    case 1:
                        $counts = Catalog::count_songs();
                        $meta = array(
                            'id'			=> $root . '/playlists',
                            'parentID'		=> $root,
                            'childCount'	=> $counts['playlists'],
                            'dc:title'		=> T_('Playlists'),
                            'upnp:class'	=> 'object.container',
                        );
                    break;

                    case 2:
                        $playlist = new Playlist($pathreq[1]);
                        if ($playlist->id) {
                            $playlist->format();
                            $meta = self::_itemPlaylist($playlist, $root . '/playlists');
                        }
                    break;
                }
            break;

            case 'smartplaylists':
                switch (count($pathreq)) {
                    case 1:
                        $counts = Catalog::count_songs();
                        $meta = array(
                            'id'			=> $root . '/smartplaylists',
                            'parentID'		=> $root,
                            'childCount'	=> $counts['smartplaylists'],
                            'dc:title'		=> T_('Smart Playlists'),
                            'upnp:class'	=> 'object.container',
                        );
                    break;

                    case 2:
                        $playlist = new Search('song', $pathreq[1]);
                        if ($playlist->id) {
                            $playlist->format();
                            $meta = self::_itemSmartPlaylist($playlist, $root . '/smartplaylists');
                        }
                    break;
                }
            break;

            default:
                $meta = array(
                    'id'			=> $root,
                    'parentID'		=> '0',
                    'childCount'    => '5',
                    'dc:title'		=> T_('Music'),
                    'upnp:class'	=> 'object.container',
                );
            break;
        }

        return $meta;
    }

    public static function _musicChilds($prmPath, $prmQuery)
    {
        $mediaItems = array();
        $queryData = array();
        parse_str($prmQuery, $queryData);

        $parent = 'amp://music' . $prmPath;
        $pathreq = explode('/', $prmPath);
        if ($pathreq[0] == '' && count($pathreq) > 0) {
            array_shift($pathreq);
        }

        switch ($pathreq[0]) {
            case 'artists':
                switch (count($pathreq)) {
                    case 1: // Get artists list
                        $artists = Catalog::get_artists();
                        foreach ($artists as $artist) {
                            $artist->format();
                            $mediaItems[] = self::_itemArtist($artist, $parent);
                        }
                    break;
                    case 2: // Get artist's albums list
                        $artist = new Artist($pathreq[1]);
                        if ($artist->id) {
                            $album_ids = $artist->get_albums();
                            foreach ($album_ids as $album_id) {
                                $album = new Album($album_id);
                                $album->format();
                                $mediaItems[] = self::_itemAlbum($album, $parent);
                            }
                        }
                    break;
                }
            break;

            case 'albums':
                switch (count($pathreq)) {
                    case 1: // Get albums list
                        $album_ids = Catalog::get_albums();
                        foreach ($album_ids as $album_id) {
                            $album = new Album($album_id);
                            $album->format();
                            $mediaItems[] = self::_itemAlbum($album, $parent);
                        }
                    break;
                    case 2: // Get album's songs list
                        $album = new Album($pathreq[1]);
                        if ($album->id) {
                            $song_ids = $album->get_songs();
                            foreach ($song_ids as $song_id) {
                                $song = new Song($song_id);
                                $song->format();
                                $mediaItems[] = self::_itemSong($song, $parent);
                            }
                        }
                    break;
                }
            break;

            case 'songs':
                switch (count($pathreq)) {
                    case 1: // Get songs list
                        $catalogs = Catalog::get_catalogs();
                        foreach ($catalogs as $catalog_id) {
                            $catalog = Catalog::create_from_id($catalog_id);
                            $songs = $catalog->get_songs();
                            foreach ($songs as $song) {
                                $song->format();
                                $mediaItems[] = self::_itemSong($song, $parent);
                            }
                        }
                    break;
                }
            break;

            case 'playlists':
                switch (count($pathreq)) {
                    case 1: // Get playlists list
                        $pl_ids = Playlist::get_playlists();
                        foreach ($pl_ids as $pl_id) {
                            $playlist = new Playlist($pl_id);
                            $playlist->format();
                            $mediaItems[] = self::_itemPlaylist($playlist, $parent);
                        }
                    break;
                    case 2: // Get playlist's songs list
                        $playlist = new Playlist($pathreq[1]);
                        if ($playlist->id) {
                            $items = $playlist->get_items();
                            foreach ($items as $item) {
                                if ($item['object_type'] == 'song') {
                                    $song = new Song($item['object_id']);
                                    $song->format();
                                    $mediaItems[] = self::_itemSong($song, $parent);
                                }
                            }
                        }
                    break;
                }
            break;

            case 'smartplaylists':
                switch (count($pathreq)) {
                    case 1: // Get playlists list
                        $pl_ids = Search::get_searches();
                        foreach ($pl_ids as $pl_id) {
                            $playlist = new Search('song', $pl_id);
                            $playlist->format();
                            $mediaItems[] = self::_itemPlaylist($playlist, $parent);
                        }
                    break;
                    case 2: // Get playlist's songs list
                        $playlist = new Search('song', $pathreq[1]);
                        if ($playlist->id) {
                            $items = $playlist->get_items();
                            foreach ($items as $item) {
                                if ($item['object_type'] == 'song') {
                                    $song = new Song($item['object_id']);
                                    $song->format();
                                    $mediaItems[] = self::_itemSong($song, $parent);
                                }
                            }
                        }
                    break;
                }
            break;

            default:
                $counts = Catalog::count_songs();

                $mediaItems[] = self::_musicMetadata('artists');
                $mediaItems[] = self::_musicMetadata('albums');
                $mediaItems[] = self::_musicMetadata('songs');
                $mediaItems[] = self::_musicMetadata('playlists');
                $mediaItems[] = self::_musicMetadata('smartplaylists');
            break;
        }

        return $mediaItems;
    }

    public static function _callSearch($criteria)
    {
        // Not supported yet
        return array();
    }

    private static function _itemArtist($artist, $parent)
    {
        return array(
            'id'			=> 'amp://music/artists/' . $artist->id,
            'parentID'		=> $parent,
            'childCount'	=> $artist->albums,
            'dc:title'		=> $artist->f_name,
            'upnp:class'	=> 'object.container',
        );
    }

    private static function _itemAlbum($album, $parent)
    {
        $api_session = (AmpConfig::get('require_session')) ? Stream::$session : false;
        $art_url = Art::url($album->id, 'album', $api_session);

        return array(
            'id'			=> 'amp://music/albums/' . $album->id,
            'parentID'		=> $parent,
            'childCount'	=> $album->song_count,
            'dc:title'		=> $album->f_title,
            'upnp:class'	=> 'object.container',
            //'upnp:album_art'=> $art_url,
        );
    }

    private static function _itemPlaylist($playlist, $parent)
    {
        return array(
            'id'			=> 'amp://music/playlists/' . $playlist->id,
            'parentID'		=> $parent,
            'childCount'	=> count($playlist->get_items()),
            'dc:title'		=> $playlist->f_name,
            'upnp:class'	=> 'object.container',
        );
    }

    private static function _itemSmartPlaylist($playlist, $parent)
    {
        return array(
            'id'			=> 'amp://music/smartplaylists/' . $playlist->id,
            'parentID'		=> $parent,
            'childCount'	=> count($playlist->get_items()),
            'dc:title'		=> $playlist->f_name,
            'upnp:class'	=> 'object.container',
        );
    }

    private static function _itemSong($song, $parent)
    {
        $api_session = (AmpConfig::get('require_session')) ? Stream::$session : false;
        $art_url = Art::url($song->album, 'album', $api_session);

        $fileTypesByExt = self::_getFileTypes();
        $arrFileType = $fileTypesByExt[$song->type];

        return array(
            'id'			=> 'amp://music/songs/' . $song->id,
            'parentID'		=> $parent,
            'dc:title'		=> $song->f_title,
            'upnp:class'	=> (isset($arrFileType['class'])) ? $arrFileType['class'] : 'object.item.unknownItem',
            //'upnp:album_art'=> $art_url,
            'dc:date'       => date("c", $song->addition_time),
            'res'           => Song::play_url($song->id),
            'size'          => $song->size,
            'protocolInfo'  => $arrFileType['mime'],
        );
    }

    private static function _getFileTypes()
    {
        return array(
            'wav' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-wav:*',
            ),
            'mpa' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/mpeg:*',
            ),
            '.mp1' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/mpeg:*',
            ),
            'mp3' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/mpeg:*',
            ),
            'aiff' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-aiff:*',
            ),
            'aif' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-aiff:*',
            ),
            'wma' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-ms-wma:*',
            ),
            'lpcm' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/lpcm:*',
            ),
            'aac' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-aac:*',
            ),
            'm4a' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-m4a:*',
            ),
            'ac3' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-ac3:*',
            ),
            'pcm' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/lpcm:*',
            ),
            'flac' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/flac:*',
            ),
            'ogg' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:application/ogg:*',
            ),
            'mka' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-matroska:*',
            ),
            'mp4a' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/x-m4a:*',
            ),
            'mp2' => array(
                'class' => 'object.item.audioItem',
                'mime' => 'file-get:*:audio/mpeg:*',
            ),
            'gif' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/gif:*',
            ),
            'jpg' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/jpeg:*',
            ),
            'jpe' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/jpeg:*',
            ),
            'png' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/png:*',
            ),
            'tiff' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/tiff:*',
            ),
            'tif' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/tiff:*',
            ),
            'jpeg' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/jpeg:*',
            ),
            'bmp' => array(
                'class' => 'object.item.imageItem',
                'mime' => 'file-get:*:image/bmp:*',
            ),
            'asf' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-ms-asf:*',
            ),
            'wmv' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-ms-wmv:*',
            ),
            'mpeg2' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2:*',
            ),
            'avi' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-msvideo:*',
            ),
            'divx' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-msvideo:*',
            ),
            'mpg' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'm1v' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'm2v' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'mp4' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mp4:*',
            ),
            'mov' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/quicktime:*',
            ),
            'vob' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/dvd:*',
            ),
            'dvr-ms' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-ms-dvr:*',
            ),
            'dat' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'mpeg' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'm1s' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg:*',
            ),
            'm2p' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2:*',
            ),
            'm2t' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'm2ts' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'mts' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'ts' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'tp' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'trp' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'm4t' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2ts:*',
            ),
            'm4v' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/MP4V-ES:*',
            ),
            'vbs' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2:*',
            ),
            'mod' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mpeg2:*',
            ),
            'mkv' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/x-matroska:*',
            ),
            '3g2' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mp4:*',
            ),
            '3gp' => array(
                'class' => 'object.item.videoItem',
                'mime' => 'file-get:*:video/mp4:*',
            ),
        );
    }
}
