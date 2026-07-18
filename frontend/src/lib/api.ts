// Typed fetch wrapper for the ViscosiGuide PHP API.
// Same-origin in production (/api); override with PUBLIC_API_BASE for split dev.

const API_BASE: string =
  (import.meta.env.PUBLIC_API_BASE as string | undefined)?.replace(/\/$/, '') || '/api';

export interface User {
  id: number;
  email: string;
  display_name: string;
  email_verified: boolean;
  notify_new_buddy: boolean;
}

export interface Spot {
  id: number;
  name: string;
  description: string | null;
  category: string;
  lat: number;
  lng: number;
  address: string | null;
  logo: string | null;
  location_url: string | null;
  website: string | null;
  price_level: number;
  created_by: number | null;
  created_by_name: string | null;
  created_at: string;
  rating_count: number;
  avg_rating: number | null;
  avg_price: number | null;
  avg_bang: number | null;
}

export interface Rating {
  id: number;
  rating: number;
  price_rating: number;
  bang_for_buck: number;
  comment: string | null;
  author: string;
  created_at: string;
}

export interface BuddyProposal {
  id: number;
  message: string | null;
  spot_id: number | null;
  spot_name: string | null;
  author: string;
  created_at: string;
}

export type GrillChoice = 'beef' | 'pork' | 'veg' | 'other';

export interface GrillOrder {
  name: string;
  choice: GrillChoice;
  custom_text: string | null;
  bring_own: boolean;
  created_at: string;
}

export interface GrillSummary {
  beef: number;
  pork: number;
  veg: number;
  other: number;
  bring_own: number;
}

export interface Buddy {
  id: number;
  type: 'lunch' | 'grill';
  title: string;
  craving: string | null;
  spot_id: number | null;
  spot_name: string | null;
  desired_time: string | null;
  location_note: string | null;
  status: string;
  expires_at: string | null;
  is_expired?: boolean;
  created_at: string;
  host_id: number;
  host_name: string;
  participant_count: number;
  participants?: { name: string; joined_at: string }[];
  proposals?: BuddyProposal[];
  orders?: GrillOrder[];
  order_summary?: GrillSummary;
}

export class ApiError extends Error {
  code: string;
  status: number;
  constructor(message: string, code: string, status: number) {
    super(message);
    this.code = code;
    this.status = status;
  }
}

let csrfToken: string | null = null;

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method || 'GET').toUpperCase();
  const headers = new Headers(options.headers);

  if (method !== 'GET' && method !== 'HEAD') {
    headers.set('Content-Type', 'application/json');
    if (!csrfToken) {
      await getMe(); // lazily fetch a CSRF token bound to the session
    }
    if (csrfToken) {
      headers.set('X-CSRF-Token', csrfToken);
    }
  }

  const res = await fetch(API_BASE + path, {
    ...options,
    method,
    headers,
    credentials: 'include',
  });

  let body: any = null;
  try {
    body = await res.json();
  } catch {
    /* empty body */
  }

  if (!res.ok) {
    const err = body?.error ?? {};
    throw new ApiError(err.message || 'Request failed', err.code || String(res.status), res.status);
  }
  return body as T;
}

export async function getMe(): Promise<{ user: User | null; csrf: string }> {
  const data = await request<{ user: User | null; csrf: string }>('/auth/me');
  csrfToken = data.csrf;
  return data;
}

export function register(email: string, password: string, display_name: string) {
  return request<{ ok: true; message: string }>('/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email, password, display_name }),
  });
}

export async function login(email: string, password: string) {
  const data = await request<{ ok: true; user: User; csrf: string }>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  csrfToken = data.csrf;
  return data;
}

export function logout() {
  return request<{ ok: true }>('/auth/logout', { method: 'POST' });
}

export function verifyEmail(token: string) {
  return request<{ ok: true; message: string }>('/auth/verify?token=' + encodeURIComponent(token));
}

export function requestReset(email: string) {
  return request<{ ok: true; message: string }>('/auth/request-reset', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
}

export function resetPassword(token: string, password: string) {
  return request<{ ok: true; message: string }>('/auth/reset', {
    method: 'POST',
    body: JSON.stringify({ token, password }),
  });
}

export function updateProfile(display_name: string, notify_new_buddy: boolean) {
  return request<{ ok: true }>('/me', {
    method: 'PATCH',
    body: JSON.stringify({ display_name, notify_new_buddy }),
  });
}

export async function listSpots(): Promise<Spot[]> {
  const data = await request<{ spots: Spot[] }>('/spots');
  return data.spots;
}

export async function getSpot(id: number): Promise<Spot> {
  const data = await request<{ spot: Spot }>('/spots/' + id);
  return data.spot;
}

export async function createSpot(input: {
  name: string;
  lat: number;
  lng: number;
  category: string;
  price_level: number;
  description?: string;
  address?: string;
  logo?: string;
  location_url?: string;
  website?: string;
}): Promise<Spot> {
  const data = await request<{ spot: Spot }>('/spots', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return data.spot;
}

export async function listRatings(spotId: number): Promise<Rating[]> {
  const data = await request<{ ratings: Rating[] }>('/spots/' + spotId + '/ratings');
  return data.ratings;
}

export async function rateSpot(
  spotId: number,
  input: { rating: number; price_rating: number; bang_for_buck: number; comment?: string }
): Promise<Rating[]> {
  const data = await request<{ ratings: Rating[] }>('/spots/' + spotId + '/ratings', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return data.ratings;
}

export async function listBuddies(): Promise<Buddy[]> {
  const data = await request<{ buddies: Buddy[] }>('/buddies');
  return data.buddies;
}

export async function getBuddy(id: number): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies/' + id);
  return data.buddy;
}

export async function createBuddy(input: {
  title: string;
  type?: 'lunch' | 'grill';
  craving?: string;
  spot_id?: number | null;
  desired_time?: string | null;
  location_note?: string;
}): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return data.buddy;
}

export async function joinBuddy(id: number): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies/' + id + '/join', { method: 'POST' });
  return data.buddy;
}

export async function orderGrill(
  id: number,
  input: { choice: GrillChoice; custom_text?: string; bring_own: boolean }
): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies/' + id + '/order', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return data.buddy;
}

export async function proposeBuddy(
  id: number,
  input: { message?: string; spot_id?: number | null }
): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies/' + id + '/propose', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return data.buddy;
}

export async function closeBuddy(id: number): Promise<Buddy> {
  const data = await request<{ buddy: Buddy }>('/buddies/' + id + '/close', { method: 'POST' });
  return data.buddy;
}
