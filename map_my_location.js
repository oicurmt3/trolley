/**
 * Shared "Go to my location" control for Leaflet map pages.
 * Call attachMapMyLocationControl(map) after the map is initialised.
 */
(function (global) {
    'use strict';

    var TOAST_HIDE_MS = 5500;
    var GEO_OPTIONS = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 };
    var MIN_ZOOM_ON_LOCATE = 15;

    var locateIconSvg =
        '<svg class="map-my-location-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">' +
        '<path fill="currentColor" d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/>' +
        '</svg>';

    function showMapToast(map, message) {
        var container = map.getContainer();
        var toast = container.querySelector('.map-geo-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'map-geo-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            container.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('visible');
        if (toast._hideTimer) {
            clearTimeout(toast._hideTimer);
        }
        toast._hideTimer = setTimeout(function () {
            toast.classList.remove('visible');
        }, TOAST_HIDE_MS);
    }

    function geolocationErrorMessage(error) {
        if (!error || typeof error.code !== 'number') {
            return 'Could not get your location. Please try again.';
        }
        switch (error.code) {
            case 1: /* PERMISSION_DENIED */
                return 'Location permission was denied. You can enable it in your browser settings and try again.';
            case 2: /* POSITION_UNAVAILABLE */
                return 'Your position could not be determined. Try moving to an open area and try again.';
            case 3: /* TIMEOUT */
                return 'Finding your location took too long. Please try again.';
            default:
                return 'Could not get your location. Please try again.';
        }
    }

    function zoomForAccuracy(accuracyMeters) {
        if (!accuracyMeters || accuracyMeters <= 0) {
            return MIN_ZOOM_ON_LOCATE;
        }
        if (accuracyMeters < 30) {
            return 17;
        }
        if (accuracyMeters < 80) {
            return 16;
        }
        if (accuracyMeters < 250) {
            return 15;
        }
        if (accuracyMeters < 800) {
            return 14;
        }
        return 13;
    }

    var MyLocationControl = L.Control.extend({
        options: {
            position: 'topright'
        },

        onAdd: function (map) {
            var self = this;
            this._map = map;
            this._locating = false;
            this._userMarker = null;
            this._accuracyCircle = null;

            var wrapper = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-map-my-location');
            this._button = L.DomUtil.create('button', 'map-my-location-btn', wrapper);
            this._button.type = 'button';
            this._button.setAttribute('aria-label', 'Go to my location');
            this._button.setAttribute('title', 'Go to my location');
            this._button.innerHTML = locateIconSvg;

            L.DomEvent.disableClickPropagation(wrapper);
            L.DomEvent.disableScrollPropagation(wrapper);

            L.DomEvent.on(this._button, 'click', function (e) {
                L.DomEvent.stop(e);
                self._requestLocation();
            });

            return wrapper;
        },

        onRemove: function () {
            this._clearUserLayers();
        },

        _setLoading: function (isLoading) {
            this._locating = isLoading;
            this._button.disabled = isLoading;
            this._button.classList.toggle('is-locating', isLoading);
            this._button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        },

        _clearUserLayers: function () {
            var map = this._map;
            if (this._userMarker) {
                map.removeLayer(this._userMarker);
                this._userMarker = null;
            }
            if (this._accuracyCircle) {
                map.removeLayer(this._accuracyCircle);
                this._accuracyCircle = null;
            }
        },

        _showUserPosition: function (lat, lng, accuracy) {
            var map = this._map;
            var latlng = L.latLng(lat, lng);

            this._clearUserLayers();

            var accuracyRadius = typeof accuracy === 'number' && accuracy > 0 ? accuracy : 50;

            this._accuracyCircle = L.circle(latlng, {
                radius: accuracyRadius,
                color: '#2d84f4',
                weight: 1,
                opacity: 0.55,
                fillColor: '#2d84f4',
                fillOpacity: 0.12
            }).addTo(map);

            this._userMarker = L.circleMarker(latlng, {
                radius: 8,
                color: '#ffffff',
                weight: 3,
                fillColor: '#2d84f4',
                fillOpacity: 1
            }).addTo(map);

            this._userMarker.bindPopup('You are here');

            var targetZoom = Math.max(map.getZoom(), zoomForAccuracy(accuracyRadius));
            map.setView(latlng, targetZoom, { animate: true });
        },

        _requestLocation: function () {
            var self = this;
            var map = this._map;

            if (self._locating) {
                return;
            }

            if (typeof window !== 'undefined' && !window.isSecureContext) {
                showMapToast(
                    map,
                    'Location is only available on a secure (HTTPS) connection. Open this site via HTTPS and try again.'
                );
                return;
            }

            if (!navigator.geolocation) {
                showMapToast(map, 'Geolocation is not supported in this browser.');
                return;
            }

            self._setLoading(true);

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    self._setLoading(false);
                    var coords = position.coords;
                    self._showUserPosition(coords.latitude, coords.longitude, coords.accuracy);
                },
                function (error) {
                    self._setLoading(false);
                    showMapToast(map, geolocationErrorMessage(error));
                },
                GEO_OPTIONS
            );
        }
    });

    function attachMapMyLocationControl(map) {
        if (!map || typeof L === 'undefined') {
            console.warn('attachMapMyLocationControl: Leaflet map not available');
            return null;
        }
        var control = new MyLocationControl();
        control.addTo(map);
        return control;
    }

    global.attachMapMyLocationControl = attachMapMyLocationControl;
})(typeof window !== 'undefined' ? window : this);