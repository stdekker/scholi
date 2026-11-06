/**
 * Posting JSON to the server, as the bubble games do for a finished round's result and its
 * practice time.
 *
 * Pure ES module: no DOM access and no bare (importmap) imports. `fetch` is injectable so tests
 * can run without a browser.
 */

/**
 * POSTs `payload` as JSON with the page's cookies. The request outlives a page that is being left
 * (`keepalive`). Rejects when the request fails or the server does not answer 2xx.
 *
 * @param {string} url
 * @param {unknown} payload
 * @param {typeof fetch} [fetchFn]
 * @returns {Promise<Response>}
 */
export async function postJson(url, payload, fetchFn = fetch) {
    const response = await fetchFn(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
        keepalive: true,
    });
    if (!response.ok) {
        throw new Error(`POST ${url} failed: HTTP ${response.status}`);
    }

    return response;
}
