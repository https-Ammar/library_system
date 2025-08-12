window.addEventListener("load", () => {
  const lod_file = document.getElementById("lod_file");
  const loading = document.getElementById("loading");
  if (lod_file && loading) {
    lod_file.style.display = "block";
    loading.style.display = "none";
  }
});
