import "./hospital-card.css";

/** hospital-card.css class names (plain, readable in the DOM instead of a CSS-module hash); shared by HospitalCard and ListSkeleton. */
const styles = {
  addr: "hc-addr",
  best: "hc-best",
  boxed: "hc-boxed",
  card: "hc-card",
  district: "hc-district",
  info: "hc-info",
  meta: "hc-meta",
  name: "hc-name",
  nameRow: "hc-name-row",
  num: "hc-num",
  per: "hc-per",
  price: "hc-price",
  priceRow: "hc-price-row",
  rating: "hc-rating",
  reviews: "hc-reviews",
  rounded: "hc-rounded",
  sale: "hc-sale",
  sep: "hc-sep",
  tag: "hc-tag",
  tagItem: "hc-tag-item",
  tagPrice: "hc-tag-price",
  tags: "hc-tags",
  thumb: "hc-thumb",
  unit: "hc-unit",
  vat: "hc-vat",
  wish: "hc-wish",
} as const;
export default styles;
