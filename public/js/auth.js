const loginModal=document.getElementById("loginModal");
const signupModal=document.getElementById("signupModal");

const loginBtn=document.getElementById("loginBtn");

const showSignup=document.getElementById("showSignup");
const showLogin=document.getElementById("showLogin");

const closeModal=document.querySelector(".closeModal");
const closeSignup=document.querySelector(".closeSignup");

if(loginBtn){

loginBtn.onclick=function(){

loginModal.style.display="flex";

};

}

if(showSignup){

showSignup.onclick=function(e){

e.preventDefault();

loginModal.style.display="none";

signupModal.style.display="flex";

};

}

if(showLogin){

showLogin.onclick=function(e){

e.preventDefault();

signupModal.style.display="none";

loginModal.style.display="flex";

};

}

if(closeModal){

closeModal.onclick=function(){

loginModal.style.display="none";

};

}

if(closeSignup){

closeSignup.onclick=function(){

signupModal.style.display="none";

};

}

window.onclick=function(e){

if(e.target==loginModal){

loginModal.style.display="none";

}

if(e.target==signupModal){

signupModal.style.display="none";

}

};