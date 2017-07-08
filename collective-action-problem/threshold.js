/**
 * Created by shobhit on 08.07.17.
 */
function addNewThreshold(){
    alert('Adding new threshold');
    // Create an input type dynamically
    var element = document.createElement("input");
    //Assign different attributes to the element.
    element.setAttribute("type", "text");
    element.setAttribute("value", "");
    element.setAttribute("name", "threshold[]");
    element.setAttribute("style", "width:200px");

    label.setAttribute("style", "font-weight:normal");

// 'foobar' is the div id, where new fields are to be added
    var div = document.getElementById("t_id");

//Append the element in page (in span).
    div.appendChild(label);
    div.appendChild(element);
}
