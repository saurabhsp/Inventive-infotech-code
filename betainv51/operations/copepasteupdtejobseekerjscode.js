<script>
    let currentIndex = -1;

    document.addEventListener("keydown", function(e) {

        let items = document.querySelectorAll("#skillsDropdown .checkbox-item");

        if (items.length === 0) return;

        // DOWN ↓
        if (e.key === "ArrowDown") {
            e.preventDefault();

            currentIndex++;

            if (currentIndex >= items.length) currentIndex = 0;

            highlightItem(items);
        }

        // UP ↑
        if (e.key === "ArrowUp") {
            e.preventDefault();

            currentIndex--;

            if (currentIndex < 0) currentIndex = items.length - 1;

            highlightItem(items);
        }

        // ENTER
        if (e.key === "Enter") {
            if (currentIndex >= 0) {

                e.preventDefault();

                let checkbox = items[currentIndex].querySelector("input");

                checkbox.checked = !checkbox.checked;

                updateSkills();
            }
        }

    });

    function highlightItem(items) {

        items.forEach(item => item.style.background = "");

        items[currentIndex].style.background = "#1e293b";

        items[currentIndex].scrollIntoView({
            block: "nearest"
        });

    }

    document.querySelector(".select-box").addEventListener("focus", () => {
        document.getElementById("skillsDropdown").style.display = "block";
    });

    flatpickr("#birthdate", {
        dateFormat: "d-m-Y", // ✅ DD-MM-YYYY
        allowInput: true
    });
</script>
<script>
    function closeAllSuggestions() {
        document.getElementById("profileCitySuggestions").style.display = "none";
        document.getElementById("profileDistrictSuggestions").style.display = "none";
        document.getElementById("profileLocalitySuggestions").style.display = "none";
    }
    document.querySelectorAll("#profileCityInput, #profileDistrictInput, #profileLocalityInput")
        .forEach(function(input) {

            input.addEventListener("blur", function() {

                // delay so click on suggestion still works
                setTimeout(function() {
                    closeAllSuggestions();
                }, 150);

            });

        });
    let profileService;
    let profilePlaceService;

    let profileSelectedCity = "";
    let profileSelectedDist = "";
    let profileSelectedState = "";
    let profileSelectedCountry = "";

    // INIT
    function initProfileCityAutocomplete() {

        profileService = new google.maps.places.AutocompleteService();
        profilePlaceService = new google.maps.places.PlacesService(document.createElement('div'));

        const input = document.getElementById("profileCityInput");

        input.addEventListener("keyup", function() {

            let query = input.value;

            if (query.length < 2) return;

            profileService.getPlacePredictions({
                input: query
            }, function(predictions, status) {

                if (!predictions) return;

                showProfileCitySuggestions(predictions);
            });
        });
    }



    // SHOW DISTRICT

    document.getElementById("profileDistrictInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        profileService.getPlacePredictions({
                input: query,
                types: ["(cities)"], // ✅ ONLY CITY
                componentRestrictions: {
                    country: "in"
                }
            },
            function(predictions) {

                if (!predictions) return;

                showProfileDistrictSuggestions(predictions);

            });
    });


    function showProfileDistrictSuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileDistrictSuggestions");
        box.innerHTML = "";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                document.getElementById("profileDistrictInput").value = item.description;
                document.getElementById("profileDistrictId").value = item.description;


                profileSelectedDist = item.description;

                box.innerHTML = "";
            }

            box.appendChild(div);
        });

        box.style.display = "block";
    }


    // SHOW CITY
    function showProfileCitySuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileCitySuggestions");
        box.innerHTML = "";
        box.style.display = "block";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                let fullText = item.description;
                // ✅ FIX: store FULL string for search
                profileSelectedCity = fullText;

                document.getElementById("profileCityInput").value = cityOnly;
                document.getElementById("profileCityId").value = cityOnly;

                profileSelectedCity = cityOnly;

                // ✅ GET FULL DETAILS (IMPORTANT LIKE HEADER)
                profilePlaceService.getDetails({
                        placeId: item.place_id
                    },
                    function(place, status) {

                        if (status !== google.maps.places.PlacesServiceStatus.OK) return;

                        place.address_components.forEach(function(comp) {

                            if (comp.types.includes("administrative_area_level_1")) {
                                profileSelectedState = comp.long_name;
                            }

                            if (comp.types.includes("country")) {
                                profileSelectedCountry = comp.long_name;
                            }
                        });

                    }
                );

                box.innerHTML = "";
            };

            box.appendChild(div);
        });
    }

    // LOCALITY
    document.getElementById("profileLocalityInput").addEventListener("keyup", function() {

        let query = this.value;

        if (query.length < 2) return;

        // ✅ build search like header
        let searchQuery = query;

        // ✅ use full city string (important)
        if (profileSelectedCity) {
            searchQuery += ", " + profileSelectedCity;
        }

        if (profileSelectedState) {
            searchQuery += ", " + profileSelectedState;
        }

        if (profileSelectedCountry) {
            searchQuery += ", " + profileSelectedCountry;
        }

        profileService.getPlacePredictions({
            input: searchQuery
        }, function(predictions, status) {

            if (!predictions) return;

            showProfileLocalitySuggestions(predictions);
        });
    });

    // SHOW LOCALITY (FILTER BY CITY)
    function showProfileLocalitySuggestions(list) {
        closeAllSuggestions();
        let box = document.getElementById("profileLocalitySuggestions");
        box.innerHTML = "";
        box.style.display = "block";

        list.forEach(function(item) {

            let div = document.createElement("div");
            div.className = "suggestion-item";
            div.innerHTML = item.description;

            div.onclick = function() {

                let fullText = item.description;

                // cityOnly for cutting
                let cityOnly = document.getElementById("profileCityInput").value.toLowerCase();

                let parts = fullText.split(",");

                let result = [];

                for (let i = 0; i < parts.length; i++) {

                    // STOP BEFORE CITY (don't include city)
                    if (parts[i].toLowerCase().includes(cityOnly)) {
                        break;
                    }

                    result.push(parts[i].trim());
                }

                let localityOnly = result.join(", ");

                document.getElementById("profileLocalityInput").value = localityOnly;
                document.getElementById("profileLocalityId").value = localityOnly;

                box.innerHTML = "";
            };

            box.appendChild(div);
        });
    }

    if (result.length === 0) {
        localityOnly = parts[0].trim();
    }


    // INIT CALL
    document.addEventListener("DOMContentLoaded", function() {
        initProfileCityAutocomplete();
    });
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=
    AIzaSyCokcdTmQxRaopu75ourz-nNmZNie1wQkY&libraries=places&callback=initProfileCityAutocomplete"
    async defer></script>