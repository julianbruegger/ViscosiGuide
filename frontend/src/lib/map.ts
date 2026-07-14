// Leaflet map helpers. Leaflet is bundled from npm (no CDN). Markers use CSS
// DivIcons so no external image assets are needed (keeps the CSP tight).
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type { Spot } from './api';

// Default view: Viscosistrasse / Emmenbrücke.
export const DEFAULT_CENTER: [number, number] = [47.0806, 8.2755];
export const DEFAULT_ZOOM = 15;

export function createMap(elementId: string): L.Map {
  const map = L.map(elementId, { scrollWheelZoom: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(map);
  return map;
}

/** Colour a pin by average rating (grey when unrated). */
function pinColor(avg: number | null): string {
  if (avg == null) return '#8a795c';
  if (avg >= 4) return '#6b7a3a';
  if (avg >= 3) return '#e0a53b';
  return '#c1442e';
}

export function spotIcon(spot: Spot): L.DivIcon {
  const color = pinColor(spot.avg_rating);
  return L.divIcon({
    className: '',
    html: `<div class="vg-pin" style="background:${color}"></div>`,
    iconSize: [22, 22],
    iconAnchor: [11, 22],
    popupAnchor: [0, -20],
  });
}

/** Add markers for spots; popups link to the detail page. */
export function addSpotMarkers(map: L.Map, spots: Spot[]): L.LayerGroup {
  const group = L.layerGroup().addTo(map);
  for (const spot of spots) {
    const marker = L.marker([spot.lat, spot.lng], { icon: spotIcon(spot) });
    const stars = spot.avg_rating != null ? `★ ${spot.avg_rating.toFixed(1)}` : 'noch keine Bewertung';
    const bang = spot.avg_bang != null ? ` · Bang: ${spot.avg_bang.toFixed(1)}/5` : '';
    // Popup content is built with DOM nodes to avoid injecting user text as HTML.
    const wrap = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = spot.name;
    const meta = document.createElement('div');
    meta.textContent = `${stars}${bang}`;
    const link = document.createElement('a');
    link.href = '/spot?id=' + spot.id;
    link.textContent = 'Details ansehen →';
    wrap.append(title, document.createElement('br'), meta, document.createElement('br'), link);
    marker.bindPopup(wrap);
    group.addLayer(marker);
  }
  return group;
}
