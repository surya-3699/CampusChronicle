(function(){
  function by(id){return document.getElementById(id)}
  function bindText(input,output,fallback){
    if(!input||!output)return;
    const update=()=>{output.textContent=(input.value||'').trim()||fallback};
    input.addEventListener('input',update); update();
  }
  function bindImage(input,img){
    if(!input||!img)return;
    input.addEventListener('change',()=>{
      const f=input.files&&input.files[0];
      if(f&&f.type.indexOf('image/')===0){
        if(img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
        img.dataset.objectUrl=URL.createObjectURL(f);
        img.src=img.dataset.objectUrl;
      }
    });
  }
  function bindCount(input,out){
    if(!input||!out)return;
    const u=()=>out.textContent=String(input.files?input.files.length:0);
    input.addEventListener('change',u); u();
  }
  function bindMediaPreview(input,row,photoOut,videoOut){
    if(!input||!row)return;
    const render=()=>{
      row.innerHTML='';
      const files=Array.from(input.files||[]);
      let photos=0, videos=0;
      files.slice(0,12).forEach((file)=>{
        const card=document.createElement('div');
        card.className='cc-preview-media-item';
        const media=file.type.indexOf('video/')===0?'video':'image';
        if(media==='image'){
          photos++;
          const img=document.createElement('img');
          img.alt=file.name;
          img.src=URL.createObjectURL(file);
          img.onload=()=>URL.revokeObjectURL(img.src);
          card.appendChild(img);
        }else{
          videos++;
          const video=document.createElement('video');
          video.muted=true; video.playsInline=true; video.preload='metadata';
          video.src=URL.createObjectURL(file);
          video.onloadeddata=()=>video.currentTime=0;
          video.onended=()=>URL.revokeObjectURL(video.src);
          card.appendChild(video);
        }
        const label=document.createElement('span');
        label.textContent=file.name;
        card.appendChild(label);
        row.appendChild(card);
      });
      if(files.length>12){
        const more=document.createElement('div'); more.className='cc-preview-media-more'; more.textContent='+'+(files.length-12)+' more'; row.appendChild(more);
      }
      if(photoOut) photoOut.textContent=String(photos);
      if(videoOut) videoOut.textContent=String(videos);
      if(!files.length){
        const empty=document.createElement('div'); empty.className='cc-preview-media-empty'; empty.textContent='Selected media will preview here before saving.'; row.appendChild(empty);
      }
    };
    input.addEventListener('change',render); render();
  }
  function bindReset(form,callback){
    if(!form)return;
    form.addEventListener('reset',()=>setTimeout(callback,0));
  }
  window.initCampusChronicleEventEditor=function(){
    const form=by('eventEditorForm');
    bindText(by('eventTitle'),by('eventPreviewTitle'),'Your event title');
    bindText(by('eventVenue'),by('eventPreviewVenue'),'Add a venue');
    bindText(by('authorizedBy'),by('eventPreviewAuthorizer'),'Add who authorized this');
    bindText(by('eventDescription'),by('eventPreviewDescription'),'Your description will appear here exactly as students will see the public event summary.');
    const category=by('eventCategory'),catOut=by('eventPreviewCategory');
    if(category&&catOut){const u=()=>catOut.textContent=category.selectedOptions&&category.selectedOptions[0]&&category.selectedOptions[0].value?category.selectedOptions[0].textContent.trim():'Category'; category.addEventListener('change',u);u();}
    const dates=[by('eventDate'),by('eventTime'),by('eventEndDate'),by('eventEndTime')], outputs=[by('eventPreviewDate'),by('eventPreviewTime')];
    const updateDate=()=>{const s=dates[0]&&dates[0].value,e=dates[2]&&dates[2].value,t=dates[1]&&dates[1].value,et=dates[3]&&dates[3].value; if(outputs[0]) outputs[0].textContent=s?(e&&e!==s?s+' → '+e:s):'Choose a date'; if(outputs[1]) outputs[1].textContent=t?(et&&et!==t?t+' → '+et:t):'Choose a time';}; dates.forEach(x=>x&&x.addEventListener('input',updateDate)); updateDate();
    const banner=by('eventBanner'),bannerImg=by('eventPreviewImage');
    bindImage(banner,bannerImg);
    bindCount(by('eventPhotos'),by('eventPhotoCount')); bindCount(by('eventVideos'),by('eventVideoCount'));
    bindMediaPreview(by('eventPhotos'),by('eventPreviewMedia'),by('eventPhotoCount'),by('eventVideoCount'));
    // Videos have their own count in the event editor; keep the combined visual preview accurate.
    const syncEventMedia=()=>{
      const row=by('eventPreviewMedia'); if(!row)return;
      row.innerHTML='';
      const files=[...Array.from((by('eventPhotos')||{}).files||[]),...Array.from((by('eventVideos')||{}).files||[])];
      let photos=0,videos=0;
      files.slice(0,12).forEach(file=>{
        const card=document.createElement('div'); card.className='cc-preview-media-item';
        if(file.type.indexOf('video/')===0){ videos++; const v=document.createElement('video'); v.muted=true; v.playsInline=true; v.preload='metadata'; v.src=URL.createObjectURL(file); card.appendChild(v); }
        else { photos++; const img=document.createElement('img'); img.alt=file.name; img.src=URL.createObjectURL(file); card.appendChild(img); }
        const label=document.createElement('span'); label.textContent=file.name; card.appendChild(label); row.appendChild(card);
      });
      if(files.length>12){const more=document.createElement('div'); more.className='cc-preview-media-more'; more.textContent='+'+(files.length-12)+' more'; row.appendChild(more);}
      if(!files.length){const empty=document.createElement('div'); empty.className='cc-preview-media-empty'; empty.textContent='Selected photos and videos will preview here before saving.'; row.appendChild(empty);}
      const p=by('eventPhotoCount'),v=by('eventVideoCount'); if(p)p.textContent=String(photos); if(v)v.textContent=String(videos);
    };
    [by('eventPhotos'),by('eventVideos')].forEach(x=>x&&x.addEventListener('change',syncEventMedia)); syncEventMedia();
    bindReset(form,()=>{if(by('eventPreviewMedia')){by('eventPreviewMedia').innerHTML='<div class="cc-preview-media-empty">Selected photos and videos will preview here before saving.</div>';} });
  };
  window.initCampusChronicleGalleryEditor=function(){
    const form=by('galleryEditorForm');
    bindText(by('galleryTitle'),by('galleryPreviewTitle'),'Your gallery title'); bindText(by('galleryDescription'),by('galleryPreviewDescription'),'Your album description will appear here.'); bindText(by('galleryAuthorizedBy'),by('galleryPreviewAuthorizer'),'Add who authorized this');
    const type=by('galleryType'),out=by('galleryPreviewType'); if(type&&out){const u=()=>out.textContent=type.value||'Manual'; type.addEventListener('change',u);u();}
    bindImage(by('galleryCover'),by('galleryPreviewImage'));
    bindMediaPreview(by('galleryMedia'),by('galleryPreviewMedia'),by('galleryPreviewPhotoCount'),by('galleryPreviewVideoCount'));
    bindReset(form,()=>{if(by('galleryPreviewMedia'))by('galleryPreviewMedia').innerHTML='<div class="cc-preview-media-empty">Selected media will preview here before saving.</div>';});
  };
})();
