/**
 * Semantic API client for the Aine headless CMS.
 */

import http from "./http";
import { PROJECTS, COLLECTIONS } from "./config";

const cms = PROJECTS.cms.identifier;
const dir = PROJECTS.directory.identifier;
const articles = PROJECTS.cms.contentCollection;
const listings = PROJECTS.directory.contentCollection;

const endpoints = {
  // =================================================================
  // CMS project — articles
  // =================================================================
  getArticles: {
    type: "get",
    project: cms,
    collection: articles,
  },
  getArticle: {
    type: "get",
    project: cms,
    collection: articles,
    id: true,
  },
  searchArticles: {
    type: "get",
    project: cms,
    collection: articles,
    action: "search",
  },
  createArticle: {
    type: "post",
    project: cms,
    collection: articles,
  },
  updateArticle: {
    type: "post",
    project: cms,
    collection: articles,
    id: true,
    action: "update",
  },
  deleteArticle: {
    type: "delete",
    project: cms,
    collection: articles,
    id: true,
  },

  // =================================================================
  // CMS project — categories
  // =================================================================
  getCategories: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.categories,
  },
  getCategory: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.categories,
    id: true,
  },
  getCategoryArticles: {
    type: "get",
    project: cms,
    source: COLLECTIONS.categories,
    id: true,
    related: articles,
  },

  // =================================================================
  // CMS project — tags
  // =================================================================
  getTags: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.tags,
  },
  getTag: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.tags,
    id: true,
  },
  getTagArticles: {
    type: "get",
    project: cms,
    source: COLLECTIONS.tags,
    id: true,
    related: articles,
  },

  // =================================================================
  // CMS project — pages
  // =================================================================
  getPages: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.pages,
  },
  getPage: {
    type: "get",
    project: cms,
    collection: COLLECTIONS.pages,
    id: true,
  },

  // =================================================================
  // CMS project — portal
  // =================================================================
  getCmsPortal: {
    type: "get",
    project: cms,
    action: "portal",
  },

  // =================================================================
  // Directory project — listings
  // =================================================================
  getListings: {
    type: "get",
    project: dir,
    collection: listings,
  },
  getListing: {
    type: "get",
    project: dir,
    collection: listings,
    id: true,
  },
  searchListings: {
    type: "get",
    project: dir,
    collection: listings,
    action: "search",
  },
  createListing: {
    type: "post",
    project: dir,
    collection: listings,
  },
  updateListing: {
    type: "post",
    project: dir,
    collection: listings,
    id: true,
    action: "update",
  },
  deleteListing: {
    type: "delete",
    project: dir,
    collection: listings,
    id: true,
  },

  // =================================================================
  // Directory project — categories / locations
  // =================================================================
  getDirectoryCategories: {
    type: "get",
    project: dir,
    collection: COLLECTIONS.categories,
  },
  getCategoryListings: {
    type: "get",
    project: dir,
    source: COLLECTIONS.categories,
    id: true,
    related: listings,
  },
  getLocationListings: {
    type: "get",
    project: dir,
    source: "locations",
    id: true,
    related: listings,
  },

  // =================================================================
  // Directory project — portal
  // =================================================================
  getDirectoryPortal: {
    type: "get",
    project: dir,
    action: "portal",
  },

  // =================================================================
  // Media (CMS project by default)
  // =================================================================
  getMediaList: {
    type: "get",
    project: cms,
    resource: "media",
  },
  getMediaItem: {
    type: "get",
    project: cms,
    resource: "media",
    id: true,
  },
  uploadMedia: {
    type: "post",
    project: cms,
    resource: "media",
    action: "upload",
  },
  deleteMedia: {
    type: "delete",
    project: cms,
    resource: "media",
    id: true,
  },

  // =================================================================
  // Project info
  // =================================================================
  getCmsProject: {
    type: "get",
    project: cms,
  },
  getDirectoryProject: {
    type: "get",
    project: dir,
  },
};

/* ------------------------------------------------------------------ *
 * Locale scoping
 * ------------------------------------------------------------------ */
let currentLocale = null;
const inflight = new Map();

export function setApiLocale(locale) {
  currentLocale = locale || null;
  inflight.clear();
}

function scoped(params = {}) {
  const merged = { ...params };
  const skipLocale = merged._skipLocale === true;
  delete merged._skipLocale;
  if (currentLocale && !skipLocale) {
    merged.filters = { locale: currentLocale, ...(merged.filters || {}) };
  }
  return merged;
}

/* ------------------------------------------------------------------ *
 * Query serialization
 * ------------------------------------------------------------------ */

const OPERATORS = [
  "equals",
  "notEquals",
  "contains",
  "notContains",
  "greaterThan",
  "greaterThanOrEqual",
  "lessThan",
  "lessThanOrEqual",
  "in",
  "notIn",
  "between",
  "notBetween",
  "isEmpty",
  "notEmpty",
];

const CONTROL_PARAMS = [
  "sort",
  "limit",
  "offset",
  "state",
  "first",
  "count",
  "timestamps",
  "or",
  "query",
  "collection",
];

function formatVal(val) {
  if (Array.isArray(val)) return val.join(",");
  if (typeof val === "boolean") return val ? 1 : 0;
  if (val === null || val === undefined) return "";
  return String(val);
}

/**
 * Serialize a single filter field into one or more `filters.x=y` parts.
 * Handles scalar (equals), operator-object, and nested relation objects.
 *
 *   locale: 'zh'                              → filters.locale=zh
 *   title: { contains: 'laravel' }            → filters.title=contains.laravel
 *   category: { slug: 'tech' }                → filters.category.slug=tech
 *   category: { slug: { contains: 'tech' } }  → filters.category.slug=contains.tech
 */
function serializeFilterField(field, filter) {
  // Scalar → equals:  filters.locale=zh
  if (filter === null || typeof filter !== "object") {
    return [`filters.${field}=${encodeURIComponent(formatVal(filter))}`];
  }

  const keys = Object.keys(filter);

  // Operator object: every key is a known operator (contains, greaterThan, ...)
  //   title: { contains: 'laravel' }  →  filters.title=contains.laravel
  const isOperatorObj =
    keys.length >= 1 && keys.every((k) => OPERATORS.includes(k));
  if (isOperatorObj) {
    return Object.entries(filter).map(([op, val]) => {
      if (op === "equals") {
        return `filters.${field}=${encodeURIComponent(formatVal(val))}`;
      }
      return `filters.${field}=${op}.${encodeURIComponent(formatVal(val))}`;
    });
  }

  // Nested object → relation filter, recurse with dotted path:
  //   category: { slug: 'tech' }  →  filters.category.slug=tech
  return keys.flatMap((subField) =>
    serializeFilterField(`${field}.${subField}`, filter[subField])
  );
}

/**
 * Serialize a single condition for use inside an `or=` comma list.
 * No `filters.` prefix (the `or` param itself implies filters).
 */
function serializeCondition(field, filter) {
  if (filter === null || typeof filter !== "object") {
    return `${field}=${formatVal(filter)}`;
  }
  return Object.entries(filter)
    .map(([op, val]) => `${field}=${op}.${formatVal(val)}`)
    .join(",");
}

/**
 * Turn a params object { filters, sort, limit, or, ... } into a query
 * string using dot-notation.  Returns '' when there is nothing to send.
 */
function serializeQuery(params = {}) {
  const parts = [];

  for (const [key, value] of Object.entries(params)) {
    // Control params pass through as-is.
    if (CONTROL_PARAMS.includes(key)) {
      if (key === "or" && Array.isArray(value)) {
        const conditions = value.flatMap((group) =>
          Object.entries(group).map(([f, filter]) =>
            serializeCondition(f, filter)
          )
        );
        parts.push(`or=${encodeURIComponent(conditions.join(","))}`);
      } else if (value !== undefined && value !== null) {
        const v = typeof value === "boolean" ? (value ? 1 : 0) : value;
        parts.push(`${key}=${encodeURIComponent(v)}`);
      }
      continue;
    }

    // The `filters` object expands into filters.* parts.
    if (key === "filters" && value && typeof value === "object") {
      for (const [field, filter] of Object.entries(value)) {
        parts.push(...serializeFilterField(field, filter));
      }
      continue;
    }
  }

  return parts.join("&");
}

/* ------------------------------------------------------------------ *
 * URL building
 * ------------------------------------------------------------------ */

function buildUrl(config) {
  const { project, collection, id, related, source, action, resource } = config;
  const base = `/api/project/${project}`;

  if (resource === "media") {
    if (action === "upload") {
      return `${base}/media/upload`;
    }
    if (id) {
      return `${base}/media/${id}`;
    }
    return `${base}/media`;
  }

  if (action === "portal") {
    return `${base}/portal`;
  }

  if (source && id && related) {
    return `${base}/${source}/${id}/${related}`;
  }
  if (!collection) {
    return base;
  }

  if (action === "search") {
    return `${base}/${collection}/search`;
  }
  if (action === "update" && id) {
    return `${base}/${collection}/update/${id}`;
  }
  if (id) {
    return `${base}/${collection}/${id}`;
  }
  return `${base}/${collection}`;
}

/* ------------------------------------------------------------------ *
 * Generic request entry
 * ------------------------------------------------------------------ */

/**
 * Make an API request from a full config object.
 *
 * @param {Object} config
 * @param {'get'|'post'|'delete'} config.type
 * @param {string} config.project      - project identifier
 * @param {string} [config.collection] - collection slug
 * @param {number} [config.id]         - record ID (single / update / delete)
 * @param {string} [config.action]     - 'search' | 'portal' | 'update' | 'upload'
 * @param {string} [config.source]     - source collection (relation queries)
 * @param {string} [config.related]    - related collection (relation queries)
 * @param {string} [config.resource]   - 'media'
 * @param {Object} [config.params]     - GET query params (filters, sort, limit, ...)
 * @param {Object|FormData} [config.data] - POST body
 * @returns {Promise<Object|null>} the backend `data` payload, or null on failure
 */
async function request(config) {
  const { type, params = {}, data = {} } = config;
  const url = buildUrl(config);

  if (type === "get") {
    const scopedParams = scoped(params);
    const query = serializeQuery(scopedParams);
    const fullUrl = query ? `${url}?${query}` : url;

    if (inflight.has(fullUrl)) {
      return inflight.get(fullUrl);
    }

    const promise = http.get(fullUrl).then((response) => {
      return response ? response.data : null;
    });
    inflight.set(fullUrl, promise);
    try {
      const result = await promise;
      return result ? result.data : null;
    } finally {
      inflight.delete(fullUrl);
    }
  }

  if (type === "post") {
    const response = await http.post(url, data); // data is JSON object or FormData
    return response ? (response.data ? response.data.data : null) : null;
  }

  if (type === "delete") {
    const response = await http.delete(url);
    return response ? (response.data ? response.data.success : false) : false;
  }

  throw new Error(`[api] Unsupported request type: ${type}`);
}

/* ------------------------------------------------------------------ *
 * Semantic shortcut factory
 * ------------------------------------------------------------------ */

/**
 * Turn an endpoint config into a callable function.
 *
 * - GET without id:  fn(params)
 * - GET with id:     fn(id, params)
 * - POST without id: fn(data)
 * - POST with id:    fn(id, data)
 * - DELETE with id:  fn(id)
 */
function createEndpointMethod(endpointConfig) {
  return function (...args) {
    const config = { ...endpointConfig };
    const needsId = endpointConfig.id === true;

    if (needsId) {
      config.id = args[0];
      const payload = args[1] || {};
      if (config.type === "get") config.params = payload;
      else config.data = payload;
    } else {
      const payload = args[0] || {};
      if (config.type === "get") config.params = payload;
      else config.data = payload;
    }

    // Remove the config-only `id` marker so buildUrl sees only real values.
    if (endpointConfig.id === true && config.id === undefined) {
      delete config.id;
    }

    return request(config);
  };
}

/* ------------------------------------------------------------------ *
 * Assemble the public api object
 * ------------------------------------------------------------------ */

const api = {
  /** Generic request entry (escape hatch for one-off calls). */
  request,

  /** Query-string serializer (exported for debugging / testing). */
  serializeQuery,
};

// Auto-generate every semantic shortcut from endpoints.js.
for (const [name, config] of Object.entries(endpoints)) {
  api[name] = createEndpointMethod(config);
}

/* ------------------------------------------------------------------ *
 * Backwards-compatible aliases (deprecated, will be removed)
 * ------------------------------------------------------------------ */

/**
 * @deprecated Use api.getArticles() / api.getListings() instead.
 */
api.collection = function (projectIdentifier, slug, params = {}) {
  return request({
    type: "get",
    project: projectIdentifier,
    collection: slug,
    params,
  });
};

/**
 * @deprecated Use api.getCategoryArticles() / api.getCategoryListings() instead.
 */
api.related = function (
  projectIdentifier,
  sourceSlug,
  id,
  relatedSlug,
  params = {}
) {
  return request({
    type: "get",
    project: projectIdentifier,
    source: sourceSlug,
    id,
    related: relatedSlug,
    params,
  });
};

export { api };
export default api;
